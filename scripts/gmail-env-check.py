from pathlib import Path
import json
import re

path=Path(__file__).resolve().parents[1]/'.env'
text=path.read_text(encoding='utf-8-sig')
match=re.search(r'^MAIL_PASSWORD=(.*)$',text,re.M)
value=match[1].strip() if match else ''
if len(value)>=2 and value[0]==value[-1] and value[0] in ['"',"'"]:
    value=value[1:-1]
normalized=re.sub(r'\s+','',value)
valid=bool(re.fullmatch(r'[A-Za-z0-9]{16}',normalized))
if valid:
    # Google displays grouped app passwords. Spaces are not part of the secret.
    updated=re.sub(r'^MAIL_PASSWORD=.*$',lambda _: 'MAIL_PASSWORD='+normalized,text,flags=re.M)
    if updated!=text: path.write_text(updated,encoding='utf-8')
elif match and value and not match[1].strip().startswith(('"',"'")) and any(c.isspace() for c in value) and "'" not in value:
    # Keep malformed private values from appearing in framework parser errors.
    updated=re.sub(r'^MAIL_PASSWORD=.*$',lambda _: "MAIL_PASSWORD='"+value+"'",text,flags=re.M)
    path.write_text(updated,encoding='utf-8')
print(json.dumps({'password_present':bool(value),'gmail_app_password_format_valid':valid,'password_value_displayed':False}))
raise SystemExit(0 if valid else 1)
