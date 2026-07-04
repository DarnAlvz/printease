# PrintEase E-Printing System

A mobile and web-based e-printing system for print shops in Calbayog City.

## Tech Stack
- HTML5
- Tailwind CSS
- CSS3
- JavaScript
- PHP
- MySQL
- PWA
- XAMPP
- GitHub

## Security Configuration

Rate limiting uses `REMOTE_ADDR` by default and ignores client-supplied `X-Forwarded-For` headers. If PrintEase is deployed behind a reverse proxy that sanitizes forwarding headers, configure exact trusted proxy IPs only:

```env
TRUSTED_PROXIES=127.0.0.1,::1
```

Leave `TRUSTED_PROXIES` unset for Laragon, XAMPP, shared hosting, or any setup where no trusted reverse proxy is normalizing `X-Forwarded-For`.

Important note: kapag may bagong Tailwind classes kang dinagdag sa PHP files, run this again:
    npm run build:tailwind