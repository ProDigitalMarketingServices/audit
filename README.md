# Audit
1. Simply put give it a url
2. Wait for the magic to happen
  - checks the url recursively for internal links
  - returns a comprehensive SEO report for each page on the site found
*Ignores urls that aren't text/html
**Ignores robots.txt
**Ignores nofollow

- written in plain old php (efficiently)
- stores information in a database for later use
- accessible over https returns json info for:
  - raw page data (title, description, images, links, word count, etc)
  - 