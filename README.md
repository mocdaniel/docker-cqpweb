# Docker-CQPweb

This repository contains the necessary Dockerfile, source code and Scripts to build an all-in-one image for the usage of [CQPweb](http://cwb.sourceforge.net/cqpweb.php). If you want to compile different versions of CQPweb's source code, [get them here](http://cwb.sourceforge.net/download.php).

This Readme is meant to provide a short overview over its configuration as well as installation process and known _"gotchas"_.

## Features
This container features a full stack of CQPweb and its surroundings, including
* CQPweb
* CWB (also usable from the command line)
* Automated SSL (re-)certification of the web service
* High configurability - even after the container's instantiation

## Technical information
This container is trying to stick to the _best practices_ mentioned in the official CQPweb documentation for the [upcoming version 3.3.0+](http://cwb.sourceforge.net/files/CQPwebAdminManual.pdf) already. However, the currently used version in this image and Dockerfile might differ. At the moment, the used versions are:

* **Ubuntu LTS:**: v.18.04 
* **CQPWeb**: v3.2.41
* **CWB**: v3.4.19
* **CQP Perl API**: 
  * Perl-CWB: v3.0.3
  * Perl-CWB-CL: v3.4.12+
  * Perl-CWB-CQI: v3.0
  * Perl-CWB-Web: v3.0
* **Apache2**: v2.4.29(Ubuntu)
* **PHP**: v7.2.24-0ubuntu0-18.04.3
* **MySQL**: 
  * Server: v5.7.29-0ubuntu0-18.04.1
  * Client: mysqlnd-5.0.12-dev
  
## Installation
### Pulling the image from DockerHub
There will always be a precompiled image built with these files here on DockerHub. You can pull it without any extra work by using

    docker pull dbodky/cqpweb:latest

### Building your own image
Alternatively, you can always pull this repository in order to configure and tweak your own image to your purposes by using
    
    git clone https://https://github.com/mocdaniel/docker-cqpweb.git
    
Before building your image you may want to configure the used setup variables located at `setup-scripts/run_cqp` which define the credentials of the admin user, database user, email and domain needed for SSL-certification etc.

Then build the image.

    docker image build -t imagename:version .
    
## First start
After getting/building the needed image, you can run your image in a container instance, optionally providing additional setup information via *environment variables*. Basically, all of the usable variables are optional and populated with default values, but some are heavily suggested. In the table below you can see their default values plus additional notes where useful. If you already set these variables before building the image, you do not need to define them at startup again.

Rule of thumb is, **if you can't derive the function from the variables' names, just stick to the default values, they *should* suffice:**

| **Variable Name**      | **Default Value**    | **Additional Notes** |
|------------------------|----------------------|----------------------|
| PHP_MAX_FILE_SIZE      | 80M                  |Recommended default value.|
| PHP_MAX_POST_SIZE      | 80M                  |Recommended default value. Must be **at least** PHP_MAX_FILE_SIZE                      |
| PHP_MEMORY_LIMIT       | 1024M                |Recommended default value                      |
| PHP_MAX_EXECUTION_TIME | 60                   |Recommended default value                      |
| DB_USER                | cqpweb_user          |Username of the internal database user CQPweb uses to query the database                      |
| DB_USER_PASSWORD       | cqpweb_password      |Password for DB_USER                      |
| DB_NAME                | cqpweb_db            |Name of the database CQPweb creates and populates                      |
| CQPWEB_USER            | admin                |**Change this.** Username for the admin user of CQPWeb                      |
| CQPWEB_USER_PASSWORD   | cqpwebsecurepassword |**Change this.** Password for CQPWEB_USER                      |
| FQDN_NAME              | **NOT SET**          |**Set this for SSL encryption of CQPweb.** Only possible if you own a registered domain, see below.                     |
| FQDN_EMAIL             | **NOT SET**          |**Set this for SSL encryption of CQPweb.** The email address used for registration with and certification by *Let's encrypt*                      |

For example, a container instantiation could look as follows:

    docker run -d -p 80:80 -p 443:443 --env FQDN_NAME=cqp.mydomain.com --env FQDN_EMAIL=admin@mydomain.com --env CQPWEB_USER=bigboss --env CQPWEB_USER_PASSWORD=insanely_secure_password dbodky/cqpweb:latest
    
**THE FIRST START OF A CONTAINER WILL NEED 1-2 MINUTES DEPENDING ON YOUR PHYSICAL HOST AND INTERNET CONNECTIVITY DUE TO FIRST-TIME SETUP AND SSL-CERTIFICATION**
    
This command runs the container _detached_ (-d, means in the background), maps the container's ports 80 and 443 to the respective ports of the physical host (-p, 443 is only needed if you are going to use **SSL encryption**) and declares the domain you are going to use for the SSL-encrypted webpage, a corresponding email address for registration and an admin account for CQPweb via environment variables (--env).

If you want to set a lot of environment variables, you can pass a *variable file* to Docker as well (An **example file** with the default values can be found at `setup-scripts/example_envs.list` in this repository).

    docker run -d -p 80:80 -p 443:443 --env-file ./setup-scripts/example_envs.list dbodky/cqpweb:latest
    
 ## SSL Encryption
 If you are planning to open the container to the internet and other users, you **should definitely** use SSL encryption. Using [Let's Encrypt](https://letsencrypt.org) and [Certbot](https://certbot.eff.org/) for creating trusted SSL certificates automatically is free even for companies and organisations and protects your users' credentials from being stolen and abused.
 
All you need to do so is a valid FQDN (*fully qualified domain name*) and the DNS-entries going with it. Set the two corresponding environment variables FQDN_NAME and FQDN_EMAIL (see above), everything else is taken care of by the container.

Certificates issued by Let's Encrypt are valid for **90 days**; The renewal is done automatically upon container (re-)start once there is **one** week (or less) left on the current certificate's validity or it has run out already. 

## Further Configuration
If you know your way around Ubuntu, Apache etc. you can do further configuration at any time as long as you got access to the physical host of the running container in one way or the other.

Just log into the container as root from the physical host's terminal by starting an interactive bash session

    docker exec -it container_id bash
    
Some useful tools for working on the command line such as *Vim* are preinstalled, of course you can install additional software.

## Known issues 
* Setting up a fully working SMTP mail server within a non-dedicated docker container can be mildly annoying. Therefore sending emails from within the container does not work as of now. When creating new users, make sure to choose the option **"No, auto-verify the account"**.
* At very rare occasions, **MySQL** is known to not start upon container start due to *PID-file leftovers* from the last session. In this case, you need to enter your container and start it manually 
    ```
    docker exec -it container_id bash
    service mysqld restart
    ```
