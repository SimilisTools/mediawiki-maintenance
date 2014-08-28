#!/usr/bin/env perl

#Quick and dirty Perl Bash script for updating MediaWiki

my $dir1 = shift; # New
my $dir2 = shift; # Old

#Files?
system("cp -r $dir2/images $dir1");
system("cp $dir2/LocalSettings.php $dir1");
system("cp $dir2/composer.json $dir1");

my ( @compdir ) = ( "skins", "extensions" );

foreach my $comp ( @compdir ) { 
	
	# Not correct below
	my $str = `diff $dir1/$comp $dir2/$comp | sort | grep 'Only' | grep '\.\.' | cut -d ' ' -f 4`;

	my (@arr) = split("\n", $str);

	foreach my $dir ( @arr ) {

		system("cp -r $dir2/$comp/$dir $dir1/$comp");
	}

}

#More thing to do?
# cd $dir1
# composer.phar update
# php maintenance/update.php
# php extensions/SemanticMediaWiki/maintenance/SMW_refreshData.php -d 50 -v

