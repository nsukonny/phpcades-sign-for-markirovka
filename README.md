# phpcades-sign-for-markirovka

Signature a data token by client key, for work with Markirovka API requests by phpcades

Sep 1 Setup phpcades extension for PHP

https://docs.cryptopro.ru/cades/phpcades/phpcades-install

Step 2 Install keys on Ubuntu VPS

Check php user on the server

```bash
ps aux | grep php-fpm
```

Import pfx key for this user (www-data in our case)

```bash
sudo -u www-data /opt/cprocsp/bin/amd64/certmgr -install -pfx -file /var/www/cryptapi/public/keys/key.pfx -pin 'YOUR KEY PIN' -store uMy
```

Setup container password, it will need for use in ENV['CONTAINER_PASS']

Recheck certificate

```bash
sudo -u www-data /opt/cprocsp/bin/amd64/certmgr -list -store uMy
```

Install CA certificate

```bash
curl -L 'http://pki.tax.gov.ru/crt/ca_fns_russia_2024_01.crt' -o /tmp/ca_fns_russia_2024_01.crt
sudo /opt/cprocsp/bin/amd64/certmgr -inst -store mCa -file /tmp/ca_fns_russia_2024_01.crt
```

Link cer to our key

```bash
sudo -u www-data /opt/cprocsp/bin/amd64/certmgr -inst -store uMy -file /var/www/cryptapi/public/keys/exported_cert.cer -cont '\\.\HDIMAGE\YOUR_IMAGE_NAME'
sudo -u www-data /opt/cprocsp/bin/amd64/certmgr -list -store uMy
```

Use

```bash
sudo -u www-data /opt/cprocsp/bin/amd64/certmgr -list -store uMy
```

for find your certificate thumbprint and put it to ENV['CERT_THUMBPRINT']

### After this you can use script and get auth token for Markirovka API
