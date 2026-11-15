#!/usr/bin/env python3
"""Install pinned Linux CI scanners after checking official release-asset SHA-256 digests."""
import argparse
import hashlib
import io
from pathlib import Path
import platform
import tarfile
import urllib.request

TOOLS = {
    'gitleaks': ('https://github.com/gitleaks/gitleaks/releases/download/v8.30.1/gitleaks_8.30.1_linux_x64.tar.gz', '551f6fc83ea457d62a0d98237cbad105af8d557003051f41f3e7ca7b3f2470eb'),
    'trivy': ('https://github.com/aquasecurity/trivy/releases/download/v0.76.2/trivy_0.76.2_Linux-64bit.tar.gz', 'bc0fc726e017b8927a62b2d3531d7a84ee01769daf5c99ed866dced54089fdbf'),
}
parser = argparse.ArgumentParser()
parser.add_argument('--directory', type=Path, required=True)
parser.add_argument('--tools', nargs='+', choices=TOOLS, default=list(TOOLS))
options = parser.parse_args()
if platform.system() != 'Linux' or platform.machine() != 'x86_64':
    raise SystemExit('These pinned CI binaries target Linux x86-64.')
options.directory.mkdir(parents=True, exist_ok=True)
for name in options.tools:
    url, digest = TOOLS[name]
    with urllib.request.urlopen(url, timeout=60) as response:
        data = response.read(150000000)
    if hashlib.sha256(data).hexdigest() != digest:
        raise SystemExit(name + ': official release digest did not match')
    with tarfile.open(fileobj=io.BytesIO(data), mode='r:gz') as archive:
        entry = archive.getmember(name)
        if not entry.isfile(): raise SystemExit('Unexpected scanner archive entry')
        output = options.directory / name
        output.write_bytes(archive.extractfile(entry).read())
        output.chmod(0o755)
    print('Verified and installed ' + name)
