# AVIDMOCK — SiteGround Deployment Guide

## Upload to SiteGround

### Student Panel → my.sat.avidmock.com
Upload the entire `student-panel/` contents to the document root of my.sat.avidmock.com

### Admin Panel → admin.sat.avidmock.com
Upload the entire `admin-panel/` contents to the document root of admin.sat.avidmock.com

### Important Notes
1. All download-suffix filenames (e.g., `index (37).php`) have been cleaned
2. Profile double .php extension has been fixed
3. AI model mismatch has been fixed
4. All 10 empty API files now have working implementations
5. 3 new learn pages (quiz, results, review) are included
6. Score Badge component is in includes/score-badge.php

### .htaccess (if needed)
Both panels should already have .htaccess files on SiteGround.
If not, create one with:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```
