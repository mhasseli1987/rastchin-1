import os
import zipfile
import shutil

project = r'F:\TELEGRAM AI ADMIN PROJECT'
output = r'F:\TELEGRAM AI ADMIN PROJECT\submission\update\enzo-telegram-ai-admin-1.0.0-update.zip'

# Copy the package zip to update
src = r'F:\TELEGRAM AI ADMIN PROJECT\submission\package\enzo-telegram-ai-admin-1.0.0.zip'
if os.path.exists(output):
    os.remove(output)

shutil.copy2(src, output)
print(f'Update zip created: {output}')
print(f'Size: {os.path.getsize(output)} bytes')