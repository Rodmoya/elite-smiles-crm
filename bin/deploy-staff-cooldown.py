"""One-time, hash-guarded production repair; no patient messages are sent."""
import ftplib
import hashlib
import io
import os
from pathlib import Path
import ssl
import subprocess
import sys
import tempfile
import uuid

SOURCE_SHA = 'b785ae6e630b3475092316ecbcd8ee612432581cc3e18c53c2eec2e0723f0d0a'
TARGET = os.environ.get('FTP_SERVER_DIR', '/').rstrip('/') + '/app/leads/lead_outreach_policy.php'
OLD = b'if ($lastOut && $now - $lastOut < 48 * 3600 && !$initialFormSubmission && !$sameDaySecondTouch && !$staffManualSend) {'
NEW = b'if ($automated && $lastOut && $now - $lastOut < 48 * 3600 && !$initialFormSubmission && !$sameDaySecondTouch && !$staffManualSend) {'


def download(ftp, path):
    buffer = io.BytesIO()
    ftp.retrbinary('RETR ' + path, buffer.write)
    return buffer.getvalue()


def main():
    # Use the certificate's actual hostname, not the older FTP alias.
    with ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=45) as ftp:
        ftp.connect('single-2020.banahosting.com', 21)
        ftp.login(os.environ['FTP_USERNAME'], os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        original = download(ftp, TARGET)
        if hashlib.sha256(original).hexdigest() != SOURCE_SHA:
            raise RuntimeError('Live policy differs from the reviewed revision; no files changed. SHA256: ' + hashlib.sha256(original).hexdigest())
        if original.count(OLD) != 1:
            raise RuntimeError('Expected exactly one cooldown condition; no files changed.')
        patched = original.replace(OLD, NEW, 1)
        with tempfile.TemporaryDirectory() as temporary:
            policy = Path(temporary) / 'lead_outreach_policy.php'
            policy.write_bytes(patched)
            subprocess.run(['php', '-l', str(policy)], check=True)
            subprocess.run(['php', 'bin/verify-staff-cooldown.php', str(policy)], check=True)
        if '--check-only' in sys.argv:
            print('Live source verified and patched candidate tested; no remote files changed.')
            return
        suffix = uuid.uuid4().hex
        backup = TARGET.removesuffix('.php') + '.before-staff-cooldown-' + suffix + '.php'
        staged = TARGET.removesuffix('.php') + '.staged-' + suffix + '.php'
        ftp.storbinary('STOR ' + backup, io.BytesIO(original))
        if download(ftp, backup) != original:
            raise RuntimeError('Backup verification failed; live file unchanged.')
        ftp.storbinary('STOR ' + staged, io.BytesIO(patched))
        if download(ftp, staged) != patched:
            raise RuntimeError('Staged verification failed; live file unchanged.')
        if download(ftp, TARGET) != original:
            raise RuntimeError('Live file changed during deployment; refusing to overwrite.')
        ftp.rename(staged, TARGET)
        if download(ftp, TARGET) != patched:
            raise RuntimeError('Live verification failed; restore the recorded backup.')
        print('Verified live policy SHA256:', hashlib.sha256(patched).hexdigest())
        print('Recoverable original:', backup)
        print('Only the automation condition changed. No messages sent.')


if __name__ == '__main__':
    main()
