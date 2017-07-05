
To start with Mutasem Elayyoub (Sam) drupal version with Phing if you don't have DDKits software

www.ddkits.com


why to use DDKits Drupal Version

because : 
1- every install will update all the modules to the latest version of modules
2- Drupal would never be old (update every phing to the latest version of 8.x)
3- Free && easy to use




/////////first start building your dev env ////////////

Use composer install 
 $ php composer.phar install

Let composer build your environment with all what you need.


Install Autoconf
This may not be necessary for all systems, but it will prevent later steps from failing
sudo chown -R `whoami` /usr/local
brew install autoconf

Install Pear/Pecl
cd /usr/lib/php
sudo php install-pear-nozlib.phar
sudo pear channel-update pear.php.net
sudo pecl channel-update pecl.php.net
sudo pear config-set php_ini /private/etc/php.ini
sudo pecl config-set php_ini /private/etc/php.ini  
sudo pear upgrade-all
sudo pear config-set auto_discover 1

Edit /etc/php.ini and find the line:
;include_path = ".:/php/includes"
and change it to
include_path = ".:/usr/lib/php/pear"

Install Optional Pear libraries
pear config-set auto_discover 1
pear install PHP_CodeSniffer
pear install pear.phpunit.de/phploc
pear channel-discover pear.phpmd.org
pear channel-discover pear.pdepend.org
pear install --alldeps phpmd/PHP_PMD
Ref: http://pear.php.net/package/PHP_CodeSniffer/docs
https://github.com/sebastianbergmann/phploc
http://phpmd.org

PHPUnit
PHPUnit with PHPUnit_TicketListener_GitHub For each project, create a phpunit.xml.dist file and test that you can run phpunit from the command line

Install Phing
pear channel-discover pear.phing.info
pear install phing/phing
Create build.xml file for each project and confirm that you can run phing from the command line

Jenkins
Install Jenkins
Install following plugins for jenkins by going to Jenkins and clicking "Manage Jenkins" and then "Manage Plugins"
* phing plugin
* Checkstyle
* CloverPHP
* HTML Publisher
* JDepend
* Plot
* PMD
* Violations
* Github
* Git
Need to do some mac specific stuff: http://momo.brauchtman.net/2011/11/12/howto-install-jenkins-on-os-x-and-make-it-build-mac-stuff/ http://jenkins-php.org http://marcelog.github.com/articles/ciconfigexample.jpeg

Mongo drivers
brew install autoconf
pecl install mongo
Add to /etc/php.ini : extension=mongo.so

Install Karma http://karma-runner.github.io/0.8/index.html
brew install phantomjs
npm install karma


///////////////
if it didn’t work please install brew 

ruby -e "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install)"

then 
brew install nom



/////////////////////////


if both above ways didn’t work with you 

just use the phar file included in your folder 

php phing.phar

it will run the phing



//////////starting your installation ///////////

go to the directory of your site 
copy project.makr and build.xml there

start by cmd “ phing ” to debug “ phing -debug ”


////done////
enjoy the most updated version of fully update list Drupal 

/// files will install on pre installation folder -> will auto move to public folder inside the same folder you are installing our version.///


http://www.ddkits.com by Mutasem Elayyoub