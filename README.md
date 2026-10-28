# yatebts-rc3-nonoff

A non-official build of Yate and YateBTS (rc3 lineage) packaged for use with
bladeRF radios, with an added mbts "testinject" command for Layer 3 message
injection. The injection feature lets you craft MM, CC, RR, and SMS messages
(for example with pycrate) and send them to a handset over an active dedicated
channel, then read the handset's replies. Use it to interact with or fuzz a
phone in a controlled test setup: your own handset, in a shielded environment,
on spectrum you are authorized to use.

## Contents

- Installation
- Running
- Fine tuning
- L3 message injection (mbts testinject)
- Injection modes
- Python example using pycrate
- Message crafting notes

## Installation

Use the source code in this tree. Compile, install, and operate Yate and
YateBTS based on these instructions:

https://github.com/Nuand/bladeRF/wiki/Setting-up-Yate-and-YateBTS-with-the-bladeRF#Building_and_Installing_Yate__YateBTS

The mbts binary is standalone. After editing mbts sources, rebuild and install:

```
make -C yatebts/mbts/CLI
make -C yatebts/mbts/apps
make -C yatebts/mbts install
```

Then reload it from the Yate console with `ybts restart`.

## Running

Before starting Yate, load the FPGA. Do not rely on the autoload feature.
Find the appropriate RBF for your device version and size. For example a
bladeRF 2.0 micro xA4 is loaded as follows:

```
bladeRF-cli -l hostedxA4.rbf
sleep 1
sudo yate
```

If yate hangs or freezes, exit the program and run `sudo killall -9 mbts yate`.
Reload the FPGA, and rerun yate. If problems persist, plug the device into
another USB port.

## Fine tuning

Yate generates as many future TX bursts as possible until the TX fifos start
blocking. RadioInterface::send() is supposed to block until there is enough
buffer room between libusb, FX3, and FPGA to fit another 1024 sample buffer.

Yate expects to generate a TX burst roughly 2 subframes before its RF
transmission. To calculate how many samples 2 subframes correspond to,
multiply the following:

```
2 subframes
8 timeslots / subframe
156.25 symbols / timeslot
8 IQ samples / symbol

2 * 8 * 156.25 * 8 = 20,000 IQ samples
```

24 buffers times 1024 samples is about 24576 samples, which, factoring in some
processing lag, corresponds to Yate's expectation of sending TX bursts 2
subframes before RF transmission.

The buffer size, num buffers, and num transfers values were chosen with this
method. They may need tuning depending on the USB host controller, CPU, and OS.

To tune, define `#define BRF_DEBUG` at the top of ybladerf.cpp, then recompile
and install yate. Strong deviations from 20,000 samples are not good, but
issues really only arise visibly if the time difference drops below 5,000 or
goes negative. In that case TX bursts will not reach the FPGA in time to be
sent over the SMA port. Consider increasing num buffers and num transfers in
unison. If CPU throughput is the issue, multiply buffer_size by a factor of 2
and divide num buffers and num transfers by the same number. Increasing num
transfers helps if USB timeout errors appear. Do not increase it past 50
percent of num buffers.

## L3 message injection (mbts testinject)

The mbts `testinject` command opens a UDP socket so you can inject raw Layer 3
frames (MM, CC, RR, SMS) onto an active dedicated channel and read the handset
replies.

### Reaching the command

mbts commands are issued from the Yate rmanager console with the `mbts` prefix.
A bare command goes to Yate (and may hit the Eliza demo script), not to mbts.
So use `mbts testinject`, not `testinject`.

### How it differs from OpenBTS

mbts does not run call control; Yate does. A dedicated channel exists only while
the MS holds one for a transaction (location update, SMS, or a call).
`testinject` can create that channel for you by paging.

### Usage

```
mbts testinject                           list active dedicated channels + index
mbts testinject <index> [port] [sapi]     inject onto active channel <index>
mbts testinject wait [port] [sapi]        block for the next channel, then inject
mbts testinject page <IMSI> [port] [sapi] page the IMSI onto an SDCCH, then inject
```

Defaults: port 28670, sapi 0. SAPI 0 carries MM, CC, and RR. SAPI 3 carries
SMS CP/RP. Send the text `STOP` over the socket to end a session. When the
handset does not reply within the timeout, the socket returns the text
`TIMEOUT`.

## Injection modes

There are three ways to get a channel to inject onto.

### 1. page mode (closest to the OpenBTS testcall behaviour)

```
mbts testinject page 001010000000001
```

mbts pages the IMSI. The MS answers with a Paging Response that establishes an
SDCCH, and `testinject` latches onto it. The channel can be short-lived because
Yate has no matching transaction and will release it, but the latch still gives
an injection window. List attached handsets with:

```
nipc list registered
```

### 2. wait mode plus an externally triggered transaction

```
mbts testinject wait
```

Then trigger something: dial a number on the handset, or place a call or send
an SMS to it from the NIB web interface. `testinject` grabs the new channel.

### 3. index mode, when a transaction is already up

```
mbts testinject       (shows the index)
mbts testinject 0     (inject onto channel 0)
```

## Python example using pycrate

Install pycrate first:

```
pip install pycrate
```

```python
#!/usr/bin/env python3
import socket
from pycrate_mobile.TS24008_MM import MMIdentityRequest
from pycrate_mobile.TS24008_IE import IDTYPE_IMSI
from pycrate_mobile.NAS import parse_NAS_MO

BTS_IP = "127.0.0.1"
PORT   = 28670            # mbts testinject default

sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
sock.settimeout(3)

def inject(raw):
    "Send raw L3 bytes to the handset, return the raw reply (or b'')."
    sock.sendto(raw, (BTS_IP, PORT))
    try:
        data, _ = sock.recvfrom(1500)
        return data
    except socket.timeout:
        return b""

# Craft a downlink MM Identity Request asking for the IMSI.
req = MMIdentityRequest(val={'IDType': IDTYPE_IMSI})
print("inject:", req.to_bytes().hex())     # e.g. 051801
resp = inject(req.to_bytes())

if resp and resp != b"TIMEOUT":
    print("reply :", resp.hex())
    msg, err = parse_NAS_MO(resp)           # decode the uplink reply
    if not err:
        print(msg.show())
else:
    print("no reply (TIMEOUT or channel gone)")

sock.sendto(b"STOP", (BTS_IP, PORT))        # end the injection loop
```

Run it while a channel is available. The quickest path is to start the paged
channel in one console:

```
mbts testinject page 001010000000001
```

and run the Python script in another terminal.

## Message crafting notes

Craft any other message the same way. For example CC messages come from
`pycrate_mobile.TS24008_CC` (Setup, Alerting, Disconnect, and so on) and RR
messages from the relevant pycrate module. Whatever bytes you send are
transmitted to the handset without modification, so you control the full L3
payload.

For SMS (CP/RP) use SAPI 3, for example:

```
mbts testinject page 001010000000001 28670 3
```

Then build the SMS CP and RP layers with the pycrate SMS modules and inject the
resulting bytes the same way as above.
