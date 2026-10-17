from pathlib import Path
from zipfile import ZipFile

root=Path(__file__).resolve().parents[1]
target=root/'docs/redesign/baseline'
with ZipFile(root/'docs/redesign/source-before-redesign.zip') as z:
    for info in z.infolist():
        name=info.filename.replace('\\','/')
        if name.startswith('resources/') and not info.is_dir():
            path=(target/name).resolve()
            if not path.is_relative_to(target.resolve()): raise RuntimeError('Unsafe archive member')
            path.parent.mkdir(parents=True,exist_ok=True)
            path.write_bytes(z.read(info))
print('Original Blade source extracted to isolated baseline directory.')
