#!/usr/bin/env perl

use Parallel::ForkManager;
my $procs = 10;
my $repeat = 100;
my $iter = 1000;
my $maxiter = 10000;

my $outfile = shift;

my $pm = new Parallel::ForkManager($procs); 

for (my $i=0; $i<$maxiter; $i=$i+$iter) {

	$pm->start and next; # do the fork

	my $margin = $iter+$repeat;	

	my $file = $outfile.$i;
	my $start = $i+1;
	my $limit = $start+$margin;
	&process( $file, $start, $margin, $limit );
	
	$pm->finish; # do the exit in the child process
}

$pm->wait_all_children;


sub process {
	my $file = shift;
	my $start = shift;
	my $max = shift;
	my $limit = shift;

	open(my $fh, '<', $file);
	my $last;
	while (my $line = readline $fh) {
		if ( $line =~ /\S/ ) { $last = $line }
	}

	my $command = "php SMW_refreshData.php -v -n ".$max." -s ".$start." -b SMWSQLStore3"." &> ".$file;
	system($command);

	my $iter = 0;
	while ( $iter < $repeat ) {

		if ( $last =~ /ID\s+(\d+)/ ) {

			my $number = $1;
			if ( $number > $limit ) {
				exit;
			}
			my $command = "php SMW_refreshData.php -v -s ".$number." -b SMWSQLStore3"." &> ".$file;
			#print $command, "\n";
			system($command);

			$iter++;

		}else{
			exit;
		}
	}

}
