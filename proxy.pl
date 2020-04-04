#!/usr/bin/perl
#
# Peteris Krumins (peter@catonmat.net)
# http://www.catonmat.net  --  good coders code, great reuse
#
# A simple TCP proxy that implements IP-based access control
# It proxies data from Growatt ShineLink to the Growatt servers
# and stores relevant data in a temporary file.
#
# Modified by Ruald Ordelman to implement multiple inverters. 

use warnings;
use strict;
use YAML::XS 'LoadFile';
use IO::Socket;
use IO::Select;
use Data::Hexify;


my $ioset = IO::Select->new;
my %socket_map;
my $config = LoadFile('settings.yaml');

my @allowed_ips = ($config->{Proxy_allowd_ips}->[0], $config->{Proxy_allowd_ips}->[1],$config->{Proxy_allowd_ips}->[2],$config->{Proxy_allowd_ips}->[3],$config->{Proxy_allowd_ips}->[4]);

my $debug = 1;
my $incoming_inverterID = 0;
my $inverter1_ID = $config->{Inverter1_ID}; 
my $inverter2_ID = $config->{Inverter2_ID};
my $inverter3_ID = $config->{Inverter3_ID};
my $inverter1_output_filename = $config->{Inverter1_output_filename};
my $inverter2_output_filename = $config->{Inverter2_output_filename};
my $inverter3_output_filename = $config->{Inverter3_output_filename};

sub new_conn {
    my ($host, $port) = @_;
    return IO::Socket::INET->new(
        PeerAddr => $host,
        PeerPort => $port
    ) || die "Unable to connect to $host:$port: $!";
}

sub new_server {
    my ($host, $port) = @_;
    my $server = IO::Socket::INET->new(
        LocalAddr => $host,
        LocalPort => $port,
        ReuseAddr => 1,
        Listen    => 100
    ) || die "Unable to listen on $host:$port: $!";
}

sub new_connection {
    my $server = shift;
    my $client = $server->accept;
    my $client_ip = client_ip($client);

    unless (client_allowed($client)) {
        print "Connection from $client_ip denied.\n" if $debug;
        $client->close;
        return;
    }
    print "Connection from $client_ip accepted.\n" if $debug;

    my $remote = new_conn($config->{GrowattServer_IP}, $config->{GrowattServer_Port});
    $ioset->add($client);
    $ioset->add($remote);

    $socket_map{$client} = $remote;
    $socket_map{$remote} = $client;
}

sub close_connection {
    my $client = shift;
    my $client_ip = client_ip($client);
    my $remote = $socket_map{$client};
    
    $ioset->remove($client);
    $ioset->remove($remote);

    delete $socket_map{$client};
    delete $socket_map{$remote};

    $client->close;
    $remote->close;

    print "Connection from $client_ip closed.\n" if $debug;
}

sub client_ip {
    my $client = shift;
    return inet_ntoa($client->sockaddr);
}

sub client_allowed {
    my $client = shift;
    my $client_ip = client_ip($client);
    return grep { $_ eq $client_ip } @allowed_ips;
}

sub save_to_file {
	my $msg = shift;
	print "incoming_inverterID:", $incoming_inverterID, "\n";

	my $filename = '';

	use feature qw(switch);
	given($incoming_inverterID){
  		when($inverter1_ID) { $filename = $inverter1_output_filename; }
  		when($inverter2_ID) { $filename = $inverter2_output_filename; }
                when($inverter3_ID) { $filename = $inverter3_output_filename; }
  		default { $filename = "onbekend.txt" }
	}

	print("Filename: ", $filename, "\n");
	#my $filename = 'report.txt';

	print ("Write", Hexify($msg), "\n");
	open(my $fh, '>', $filename) or die "Could not open file '$filename' $!";
	print $fh $msg;
	close $fh;
}

#sub save_to_file2 {
#	my $msg = shift;
#	my $filename = 'report13.txt';
#	print ("Write", Hexify($msg), "\n");
#	open(my $fh, '>', $filename) or die "Could not open file '$filename' $!";
#	print $fh $msg;
#	close $fh;
#}

#string as parameter
sub decryptMsg {
   	my $msg = shift;
	$msg = substr($msg, 8);
        my $returnstring = ''; 
   	my $encryptionKey = 'Growatt';
    	my $pos = 0;
	
	#for (my $i=0; $i <= length($msg); $i++) 
	#Only first 40 characters, that's enough for the inverterID
	for (my $i=0; $i < 40; $i++)
	{		
		my $value = ord(substr($encryptionKey, $pos++, 1));
		if ($pos +1 > length($encryptionKey)) 
      		{
        		$pos = 0;
      		}

		my $workValue = (ord(substr($msg, $i, 1)) ^ $value);
		#print ("Value = ", $value, " workValue = ", $workValue, "\n");
		if ($workValue < 0) 
      		{ 
        		$workValue += 256;
      		}

		$returnstring = $returnstring . chr($workValue);
   	}
	return $returnstring;
}

print "Starting a server on 0.0.0.0:5279\n";

my $server = new_server('0.0.0.0', 5279);
$ioset->add($server);

while (1) {
    for my $socket ($ioset->can_read) {
        if ($socket == $server) {
            new_connection($server);
        }
        else {
	    next unless exists $socket_map{$socket};
            my $remote = $socket_map{$socket};
            my $buffer;
            my $read = $socket->sysread($buffer, 4096);
            if ($read) {
                $remote->syswrite($buffer);
		#print ("Read" , Hexify(\$buffer), "\n");
		print ("Messagetype received: " , Hexify(\substr($buffer, 6, 2)), "\n"); 

		if (substr($buffer, 6, 2) eq "\x01\x04" && length($buffer) > 100) {
			#print(substr($buffer, 0, 25), "\n");
			my $tempString = decryptMsg($buffer);
			#print(substr($tempString, 0, 25), "\n");
			$incoming_inverterID = substr($tempString, 30, 10); #10th character in string, and 10 characters long
			save_to_file($buffer);
		}
	
		#-------Voor als er twee type berichten binnenkomen--------
		#if (substr($buffer, 6, 2) eq "\x51\x03" && length($buffer) > 100) {
		#	save_to_file2($buffer);
		#}
            }
            else {
                close_connection($socket);
            }
        }
    }
}
