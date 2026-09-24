"""Apply the Smile Design lead-schema compatibility repair to the live file only."""
import importlib.util
from pathlib import Path

spec = importlib.util.spec_from_file_location('guarded_deployer', Path(__file__).with_name('deploy-language-reply.py'))
guard = importlib.util.module_from_spec(spec)
spec.loader.exec_module(guard)
guard.BASE = '80f16d1'
guard.TEST = 'tests/smile_design_name_correction_test.php'
guard.LABEL = 'Smile Design name correction lead-schema compatibility'
guard.FILES = {
    'app/smile_design/smile_design_service.php': '98df1a330487c1d5dcc4bfe3bec1a6d12b024cede0975d7dcab4a772b77a4069',
}

if __name__ == '__main__':
    guard.main()
