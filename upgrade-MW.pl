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
	# my $str = `diff $dir1/$comp $dir2/$comp | sort | grep 'Only' | grep '\.\.' | cut -d ' ' -f 4`;

	# Check below
	my (@arr) = &newDirelements( $dir1, $dir2 );

	foreach my $dir ( @arr ) {

		system("cp -r $dir2/$comp/$dir $dir1/$comp");
	}

}

#More thing to do?
# cd $dir1
# composer.phar update
# php maintenance/update.php
# php extensions/SemanticMediaWiki/maintenance/SMW_refreshData.php -d 50 -v


sub newDirelements {

	my $dir1 = shift;
	my $dir2 = shift;
	
	my @new = ();
	my @filedir1 = ();
	my @filedir2 = ();
	
	opendir (DIR1, $dir1) || die "cannot open $dir1";
	opendir (DIR2, $dir2) || die "cannot open $dir2";
	
	my @filedir1 =  grep { !/^\./ && -d "$dir1/$_" } readdir(DIR1);
	my @filedir2 =  grep { !/^\./ && -d "$dir2/$_" } readdir(DIR2);
	
	foreach my $filedir2 ( $dir ) {
	
		if ( grep( /^$dir$/, @filedir1 ) ) {
			push( @new, $dir );
		}
	}
	
	closedir(DIR1);
	closedir(DIR2);
	
	return @new;

}
