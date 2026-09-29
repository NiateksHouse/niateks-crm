"""Real HTTP cookies/CSRF checks against a disposable CI server, without test middleware bypass."""
import http.cookiejar
import re
import urllib.request
import urllib.parse
import urllib.error
import time

BASE = 'http://127.0.0.1:8089'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

def client():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect()), jar

def request(opener, path, data=None):
    body = None if data is None else urllib.parse.urlencode(data, doseq=True).encode()
    try:
        response = opener.open(BASE + path, body, timeout=5)
    except urllib.error.HTTPError as error:
        response = error
    return response.code, response.headers, response.read().decode()

def token(html):
    match = re.search(r'name="_token" value="([^"]+)"', html)
    assert match, 'CSRF token missing'
    return match.group(1)

opener, jar = client()
for attempt in range(30):
    try:
        status, headers, html = request(opener, '/login')
        break
    except urllib.error.URLError:
        time.sleep(1)
else:
    raise AssertionError('Server did not start')
assert status == 200
assert 'no-store' in headers.get('Cache-Control', '')
assert headers.get('X-Frame-Options') == 'DENY'
assert headers.get('X-Content-Type-Options') == 'nosniff'
assert 'default-src' in headers.get('Content-Security-Policy', '')
csrf = token(html)
assert request(opener, '/companies')[0] == 302
credentials = {'username':'http_fixture', 'password':'Disposable-http-fixture-782!'}
assert request(opener, '/login', credentials)[0] == 419, 'Missing CSRF must fail'
assert request(opener, '/login', credentials | {'_token':'invalid'})[0] == 419
assert request(opener, '/login', credentials | {'_token':csrf})[0] == 302
status, headers, html = request(opener, '/companies')
assert status == 200 and 'Firmalar' in html
csrf = token(html)
assert request(opener, '/companies', {'name':'Forbidden write'})[0] == 419
status, _, form = request(opener, '/companies/create')
assert status == 200
status, headers, _ = request(opener, '/companies', {
    '_token':token(form), 'name':'HTTP Supplier', 'country_code':'TR', 'roles[]':['customer','supplier']
})
assert status == 302
location = headers['Location'].replace(BASE, '')
status, _, html = request(opener, location)
assert status == 200 and 'HTTP Supplier' in html and 'Tedarikçi' in html
# Replay the authenticated cookie after logout: the server must reject it.
saved_cookie = '; '.join(c.name + '=' + c.value for c in jar)
assert request(opener, '/logout', {'_token':token(html)})[0] == 302
assert request(opener, '/companies')[0] == 302
replay = urllib.request.build_opener(NoRedirect())
replay.addheaders = [('Cookie', saved_cookie)]
assert request(replay, '/companies')[0] == 302
print('HTTP checks passed: guest access, CSRF, login, shared company write, security headers, logout and session replay.')

activation, activation_jar = client()
status, _, form = request(activation, '/activate')
assert status == 200 and 'Davet kodu' in form
body = {'invitation_code':'f' * 64, 'password':'HTTP-Activation-Fixture-782!', 'password_confirmation':'HTTP-Activation-Fixture-782!'}
assert request(activation, '/activate', body)[0] == 419
assert request(activation, '/activate', body | {'_token':token(form)})[0] == 302
status, _, login_page = request(activation, '/login')
assert status == 200
assert request(activation, '/login', {'_token':token(login_page), 'username':'http_invited', 'password':'HTTP-Activation-Fixture-782!'})[0] == 302
assert request(activation, '/companies')[0] == 200
print('Real HTTP invitation activation, CSRF and newly activated account login passed.')
