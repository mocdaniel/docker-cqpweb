# Use Ubuntu 22.04 as base
FROM ubuntu:20.04

#########################################################
# General docker image settings
#########################################################

# Dump everything to /tmp during image creation
WORKDIR /tmp

# Open ports 80 and 22 for apache and ssh respectively
EXPOSE 80/tcp
EXPOSE 443/tcp

# Disable MySQL binary logging as it needs tremendous amounts of disk space
ENV log_bin OFF

# Change to silent mode for installing the required packages without providing user input
ENV DEBIAN_FRONTEND noninteractive


#########################################################
# Installation and setup of everything required by cwb/cqp
#########################################################

RUN apt-get update; apt-get install -y gawk tar gzip wget subversion net-tools  apache2 perl \
libglib2.0-dev libpcre3 libreadline8 libtinfo6 vim php \
php-mysqli php-mbstring php-gd mysql-server r-base zlib1g-dev \
certbot; mkdir /docker-scripts

# change back to interactive
ENV DEBIAN_FRONTEND dialog

# Fetch the latest source files without SSL verification (needed for GH Actions)
RUN wget --no-check-certificate -O /tmp/cwb.tar.gz https://sourceforge.net/projects/cwb/files/cwb/cwb-3.5/source/cwb-3.5.0-src.tar.gz/download
RUN wget --no-check-certificate -O /tmp/cqpweb.tar.gz https://sourceforge.net/projects/cwb/files/CQPweb/CQPweb-3.2/CQPweb-3.2.43.tar.gz/download
RUN svn checkout https://svn.code.sf.net/p/cwb/code/perl/trunk /tmp/perl

# Copy all necessary setup scripts and the CQP source code into the image
COPY setup-scripts/run_cqp /docker-scripts/.
COPY setup-scripts/cqp_installation /docker-scripts/.
COPY setup-scripts/check_ssl_expiration /docker-scripts/.

WORKDIR /docker-scripts
RUN bash ./cqp_installation
ENTRYPOINT ["bash", "./run_cqp"]
