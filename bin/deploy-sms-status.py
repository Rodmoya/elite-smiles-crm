"""Deploy only verified SMS-status changes, through the existing guarded deployer."""
import importlib.util
from pathlib import Path

spec = importlib.util.spec_from_file_location('guarded_deployer', Path(__file__).with_name('deploy-language-reply.py'))
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)
deploy.BASE = 'e242ada'
deploy.FILES = {
    # Dependency first; callback second. Rollback runs in reverse order.
    'app/core/twilio.php': 'ec70b87c19ff17d429a909f794ce88d0a3cc8b16519af8e147bdedd37f7f8816',
    'app/api/twilio_sms_status.php': '5f8d58f9b47e7a933b461a604fb184096dabd54aaff6c1f3d0dc3cda0f11d620',
}
deploy.TEST = 'tests/twilio_status_order_test.php'
deploy.LABEL = 'SMS status ordering'
deploy.main()
