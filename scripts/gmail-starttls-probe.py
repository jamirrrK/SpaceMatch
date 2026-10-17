from pathlib import Path
import json
import smtplib
import ssl

report={'host':'smtp.gmail.com','port':587,'authentication_attempted':False,'message_sent':False,'certificate_verification_disabled':False,'starttls_verified':False}
try:
    with smtplib.SMTP('smtp.gmail.com',587,timeout=12) as client:
        client.ehlo()
        if not client.has_extn('starttls'): raise RuntimeError('STARTTLS unavailable')
        client.starttls(context=ssl.create_default_context())
        client.ehlo()
        report.update(starttls_verified=True,tls_version=client.sock.version(),cipher=client.sock.cipher()[0])
except Exception as error:
    report['failure_type']=type(error).__name__
Path(__file__).resolve().parents[1].joinpath('docs/gmail-starttls-probe.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
print(json.dumps(report,indent=2))
