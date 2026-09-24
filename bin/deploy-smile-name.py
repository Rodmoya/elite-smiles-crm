"""Deploy the Smile Design name correction without replacing live-only files."""
import ftplib
import hashlib
import importlib.util
import io
import os
from pathlib import Path
import socket
import ssl
import subprocess
import sys
import tempfile
import uuid

spec = importlib.util.spec_from_file_location('guarded_deployer', Path(__file__).with_name('deploy-language-reply.py'))
guard = importlib.util.module_from_spec(spec)
spec.loader.exec_module(guard)
guard.BASE = 'c758e2d'
EXISTING = {
    'app/smile_design/smile_design_service.php': '57b906982f45607854f391dd8600959035c0122340f9a0517e2c292869a258e1',
    'smile-design/cases/show.php': 'f50629bf1c0e0517d004b6e60ae1fe64253a04029afad3f7297ae42ff294c0fd',
}
NEW_ACTION = 'app/actions/smile_design_case_name_update.php'

def main():
    resolve = socket.getaddrinfo
    socket.getaddrinfo = lambda host, port, family=0, type=0, proto=0, flags=0: resolve(host, port, socket.AF_INET, type, proto, flags)
    root = os.environ.get('FTP_SERVER_DIR', '/').rstrip('/')
    with ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=45) as ftp:
        ftp.connect('single-2020.banahosting.com', 21)
        ftp.login(os.environ['FTP_USERNAME'], os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        prepared = []
        with tempfile.TemporaryDirectory() as temporary:
            for path, expected in EXISTING.items():
                remote = root + '/' + path
                original = guard.download(ftp, remote)
                if hashlib.sha256(original).hexdigest() != expected:
                    raise RuntimeError('Live source changed; no files modified: ' + path)
                candidate = guard.patch_live(original, path)
                local = Path(temporary) / Path(path).name
                local.write_bytes(candidate)
                subprocess.run(['php', '-l', str(local)], check=True)
                prepared.append((remote, original, candidate))
            remote_action = root + '/' + NEW_ACTION
            try:
                existing = guard.download(ftp, remote_action)
            except ftplib.error_perm as e:
                if not str(e).startswith('550'):
                    raise
            else:
                raise RuntimeError('New action path already exists; no files modified. SHA256: ' + hashlib.sha256(existing).hexdigest())
            action = Path(NEW_ACTION).read_bytes()
            subprocess.run(['php', '-l', NEW_ACTION], check=True)
            subprocess.run(['php', 'tests/smile_design_name_correction_test.php'], check=True)
            if '--check-only' in sys.argv:
                print('Live Smile Design patches and new action verified; no remote files changed.')
                return
            # Activate the service, then action, then visible control. Keep
            # verified backups of replaced files and roll back on any failure.
            changed = []
            created_action = False
            try:
                for remote, original, candidate in prepared[:1]:
                    install(ftp, remote, original, candidate)
                    changed.append((remote, original))
                staged_action = remote_action[:-4] + '.staged-' + uuid.uuid4().hex + '.php'
                ftp.storbinary('STOR ' + staged_action, io.BytesIO(action))
                if guard.download(ftp, staged_action) != action:
                    raise RuntimeError('New action staging verification failed')
                ftp.rename(staged_action, remote_action)
                created_action = True
                if guard.download(ftp, remote_action) != action:
                    raise RuntimeError('New action verification failed')
                for remote, original, candidate in prepared[1:]:
                    install(ftp, remote, original, candidate)
                    changed.append((remote, original))
            except Exception:
                for remote, original in reversed(changed):
                    rollback = remote[:-4] + '.rollback-' + uuid.uuid4().hex + '.php'
                    ftp.storbinary('STOR ' + rollback, io.BytesIO(original))
                    ftp.rename(rollback, remote)
                if created_action:
                    ftp.rename(remote_action, remote_action[:-4] + '.rolled-back-' + uuid.uuid4().hex + '.php')
                raise
            print('Smile Design name correction installed; no patient messages sent.')

def install(ftp, remote, original, candidate):
    suffix = uuid.uuid4().hex
    backup = remote[:-4] + '.before-name-correction-' + suffix + '.php'
    staged = remote[:-4] + '.staged-' + suffix + '.php'
    ftp.storbinary('STOR ' + backup, io.BytesIO(original))
    if guard.download(ftp, backup) != original:
        raise RuntimeError('Backup verification failed: ' + remote)
    ftp.storbinary('STOR ' + staged, io.BytesIO(candidate))
    if guard.download(ftp, staged) != candidate or guard.download(ftp, remote) != original:
        raise RuntimeError('Concurrent change or staged verification failure: ' + remote)
    ftp.rename(staged, remote)
    if guard.download(ftp, remote) != candidate:
        rollback = remote[:-4] + '.rollback-' + uuid.uuid4().hex + '.php'
        ftp.storbinary('STOR ' + rollback, io.BytesIO(original))
        ftp.rename(rollback, remote)
        raise RuntimeError('Post-deploy verification failed: ' + remote)
    print('Verified:', remote, hashlib.sha256(candidate).hexdigest(), 'Backup:', backup)

if __name__ == '__main__':
    main()
