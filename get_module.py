import base64
import sys

b64_data = sys.stdin.read().strip()

with open("reseller_api.zip", "wb") as f:
    f.write(base64.b64decode(b64_data))
print("Successfully wrote reseller_api.zip")
