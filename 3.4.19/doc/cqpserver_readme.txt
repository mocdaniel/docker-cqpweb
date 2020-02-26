
	CQPserver quick installation notes
	==================================


(1) Prerequisites: You need a machine where the 
IMS CorpusWorkbench is already installed and the corpora you need are
available within CQP. 


(2) Download the appropriate CQPserver binary

Solaris:	cqpserver-<version>-sparc-solaris
Linux:  	cqpserver-<version>-i386-linux


(3) Put the binary in the directory you want, rename it to 'cqpserver'
(or create a symbolic link) and set execute permissions:

	> chmod 755 cqpserver


(4) Now you need to write a user/password list for access control.
This file will be read by the CQP parser, so every command must end
with a semicolon ';' and you can add comments beginning with a '#'
character. There are two types of entries in the user list:

a. authorised hosts

	host <numeric IP address>;

CQPserver will only accept connections from the hosts in this list.
You can specify entire subnets by replacing the last number in the
IP address with an asterisk, e.g.

	host 141.58.127.*;

and allow access from ALL hosts with

	host *;

b. user/password entries

	user <login> "<password>";

A client tying to connect has to send one of the <login>/<password>
combinations listed in the access control file as arguments of the
CQI_CTRL_CONNECT command. You can optionally grant a user access to a
limited list of corpora only:

	user <login> "<password>" (<corpus1> <corpus2> ... );

Since passwords are not encrypted in the user list, this file should
only be visible to the person running the CQPserver. A sample user
list file is appended below.


(5) Run the CQPserver in its own terminal window. Don't run it in the
background. The CQPserver will spawn a new server process for every
established CQi connection. Server logs and debugging output from all
spawned processes are shown in the server's terminal window. You have
to specify the user list file on the command-line:

	> cqpserver -I <user_list>

Assuming that you've named your user list file <cqpserver.init> and
that both the CQPserver binary and <cqpserver.init> are located in the
current directory, you have to type

	> ./cqpserver -I cqpserver.init

To exit the CQPserver and accept no further connections, press
<Ctrl>-C (if you have run CQPserver in the background, you will have
to kill it explicitly).


(6) Some useful command line options (add these to the command lines
given above): 

	-L	accept connections from local machine only
		(affords some additional safety from outside attacks)
	-1	accept one connection only, then exit
		(so you can't accidentally forget to shut down the
		 server and leave it open to attacks)
	-P <num> By default, the CQPserver accepts CQi connections on
		the default CQI_PORT (4877). If this port is not
		available, use the -P switch to listen on port <num>.

	-d ServerDebug  enables CQPserver debugging output; in particular
		this displays all CQi commands received by the server.
		Recommended during development.

	-d Snoop  Show all network data sent or received by the
		server. For heavy debugging :o)


(7) Sample user list files:

----
# CQPserver init file

host 127.0.0.1;       # localhost should always be enabled

host 129.177.24.6;    # add single hosts
host 129.177.24.33;

host 141.58.127.*;    # add entire subnet

user aik "frechschnauze";  # format: user <name> <password>;
user poppe "pilsner"       # restrict access to certain corpora
	(MLCC-DE MLCC-EN BNC XEROX-MAN-DE XEROX-MAN-EN XEROX-MAN-FR);
----

----
# CQPserver init file for public corpus server (no access restrictions)

host *;               # allow access from any host (including localhost)

# users must login with name "anonymous" and explicit "" as password!
user anonymous ""                    # no password required,
  (PUBLIC-TEXTS-1 PUBLIC-TEXTS-2);   # but access should always be explicitly restricted to publicly available corpora
----

	
