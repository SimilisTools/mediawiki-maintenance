#!/usr/bin/env perl

my $repeat = 100;
my $file = shift;

open(my $fh, '<', $file);
my $last;
while (my $line = readline $fh) {
	if ( $line =~ /\S/ ) { $last = $line }
}

my $iter = 0;
while ( $iter < $repeat ) {

	if ( $last =~ /ID\s+(\d+)/ ) {

		my $number = $1;
		my $command = "php SMW_refreshData.php -v -s ".$number." -b SMWSQLStore3"." &> ".$file;
		#print $command, "\n";
		system($command);

		$iter++;

	}else{
		exit;
	}
}
