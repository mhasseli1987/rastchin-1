import os
import zipfile

project = r'F:\TELEGRAM AI ADMIN PROJECT'
output = r'F:\TELEGRAM AI ADMIN PROJECT\submission\package\enzo-telegram-ai-admin-1.0.0.zip'

files_to_add = []

# Main file
files_to_add.append(('enzo-telegram-ai-admin.php', os.path.join(project, 'enzo-telegram-ai-admin.php')))

# includes directory
includes_dir = os.path.join(project, 'includes')
for root, dirs, files in os.walk(includes_dir):
    for f in files:
        src = os.path.join(root, f)
        rel = os.path.relpath(src, project)
        plugin_rel = rel.replace(os.sep, '/')
        files_to_add.append((plugin_rel, src))

# Documentation files
docs = ['README.md', 'INSTALLATION.md', 'USER-GUIDE.md', 'GUIDE.md', 'ARCHITECTURE.md', 'SECURITY.md']
for d in docs:
    src = os.path.join(project, d)
    if os.path.exists(src):
        files_to_add.append((d, src))

# Remove old zip
if os.path.exists(output):
    os.remove(output)

# Create new zip
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as zf:
    for arcname, src in files_to_add:
        zf.write(src, arcname)
        print(f'Added: {arcname}')

print(f'\nTotal: {len(files_to_add)} files')
print(f'Output: {output}')

# Verify
with zipfile.ZipFile(output, 'r') as zf:
    names = zf.namelist()
    print(f'\nVerification: {len(names)} files in zip')
    for n in sorted(names)[:10]:
        print(f'  {n}')
    if len(names) > 10:
        print(f'  ... and {len(names)-10} more')