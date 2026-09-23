"""Hash-guarded focused repair preserving unrelated production changes."""
import difflib
import ftplib
import hashlib
import io
import os
from pathlib import Path
import socket
import ssl
import subprocess
import sys
import tempfile
import uuid

BASE = 'a6ce9ea'
TEST = 'tests/lead_agent_language_reply_test.php'
LABEL = 'Language reply and SMS takeover bookkeeping'
FILES = {
    'app/leads/lead_agent.php': 'b93e038fc196a01fab296f96411d92d8ea248e2fd9f9b2f7133c8d9ced35be80',
    'app/actions/lead_send_sms.php': 'ee13443322a6cc0b573a30d85b95f2cad03acccfb9c80974366bda00a8ba3832',
}

def download(ftp, path):
    data = io.BytesIO()
    ftp.retrbinary('RETR ' + path, data.write)
    return data.getvalue()

def patch_live(original, path):
    before = subprocess.check_output(['git', 'show', BASE + ':' + path]).decode().replace('\r\n', '\n')
    after = Path(path).read_text(encoding='utf-8').replace('\r\n', '\n')
    old, new = before.splitlines(keepends=True), after.splitlines(keepends=True)
    value = original.decode().replace('\r\n', '\n')
    # Group edits with context so an insertion cannot target an unrelated branch.
    for group in reversed(list(difflib.SequenceMatcher(None, old, new, autojunk=False).get_grouped_opcodes(3))):
        first, last = group[0], group[-1]
        source = ''.join(old[first[1]:last[2]])
        target = ''.join(new[first[3]:last[4]])
        if value.count(source) != 1:
            raise RuntimeError('Patch context differs: ' + path)
        value = value.replace(source, target, 1)
    if b'\r\n' in original:
        value = value.replace('\n', '\r\n')
    return value.encode()

def main():
    resolve = socket.getaddrinfo
    socket.getaddrinfo = lambda host, port, family=0, type=0, proto=0, flags=0: resolve(host, port, socket.AF_INET, type, proto, flags)
    root = os.environ.get('FTP_SERVER_DIR', '/').rstrip('/')
    with ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=45) as ftp:
        ftp.connect('single-2020.banahosting.com', 21)
        ftp.login(os.environ['FTP_USERNAME'], os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        candidates = []
        with tempfile.TemporaryDirectory() as temporary:
            for path, expected in FILES.items():
                remote = root + '/' + path
                original = download(ftp, remote)
                if hashlib.sha256(original).hexdigest() != expected:
                    raise RuntimeError('Live source changed; no files modified: ' + path)
                candidate = patch_live(original, path)
                local = Path(temporary) / Path(path).name
                local.write_bytes(candidate)
                subprocess.run(['php', '-l', str(local)], check=True)
                candidates.append((remote, original, candidate))
            subprocess.run(['php', TEST], check=True)
            if '--check-only' in sys.argv:
                print('Both live candidates verified; no remote writes or patient messages.')
                return
            changed = []
            try:
                for remote, original, candidate in candidates:
                    suffix = uuid.uuid4().hex
                    backup = remote[:-4] + '.before-language-reply-' + suffix + '.php'
                    staged = remote[:-4] + '.staged-' + suffix + '.php'
                    ftp.storbinary('STOR ' + backup, io.BytesIO(original))
                    if download(ftp, backup) != original:
                        raise RuntimeError('Backup verification failed')
                    ftp.storbinary('STOR ' + staged, io.BytesIO(candidate))
                    if download(ftp, staged) != candidate or download(ftp, remote) != original:
                        raise RuntimeError('Concurrent change or staging verification failure')
                    ftp.rename(staged, remote)
                    changed.append((remote, original))
                    if download(ftp, remote) != candidate:
                        raise RuntimeError('Post-deploy verification failed')
                    print('Verified:', remote, hashlib.sha256(candidate).hexdigest(), 'Backup:', backup)
            except Exception:
                for remote, original in reversed(changed):
                    rollback = remote[:-4] + '.rollback-' + uuid.uuid4().hex + '.php'
                    ftp.storbinary('STOR ' + rollback, io.BytesIO(original))
                    ftp.rename(rollback, remote)
                raise
            print(LABEL + ' deployed. No messages sent.')

if __name__ == '__main__':
    main()
