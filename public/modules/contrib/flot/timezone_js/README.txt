cd {Drupal base}/libraries
mkdir timezone_js
cd timezone_js
wget https://raw.githubusercontent.com/mde/timezone-js/master/src/date.js

# Create the /tz directory
mkdir tz

# Download the latest Olson files
curl ftp://ftp.iana.org/tz/tzdata-latest.tar.gz -o tz/tzdata-latest.tar.gz

# Expand the files
tar -xvzf tz/tzdata-latest.tar.gz -C tz

# Optionally, you can remove the downloaded archives.
rm tz/tzdata-latest.tar.gz
