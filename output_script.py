import subprocess
print("To generate the zip file, please run the following python script on your end:")

with open("reseller_api_flat.b64", "r") as f:
    b64_content = f.read().strip()

print('```python')
print('import base64')
print('b64_data = """' + b64_content + '"""')
print('with open("reseller_api.zip", "wb") as f:')
print('    f.write(base64.b64decode(b64_data))')
print('print("Successfully generated reseller_api.zip")')
print('```')
