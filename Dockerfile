# Use Ubuntu 18.04 as base
FROM ubuntu:18.04

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

RUN apt-get update; apt-get install -y gawk tar gzip apache2 perl \
libncurses5-dev libgtk2.0-dev libreadline-dev bison flex vim php \
php-mysqli php-mbstring php-gd mysql-server r-base zlib1g-dev \
certbot; mkdir /docker-scripts

# change back to interactive
ENV DEBIAN_FRONTEND dialog


# Copy all necessary setup scripts and the CQP source code into the image
COPY setup-scripts/run_cqp /docker-scripts/.
COPY setup-scripts/cqp_installation /docker-scripts/.
COPY setup-scripts/check_ssl_expiration /docker-scripts/.
COPY 3.4.19/ ./3.4.19/
COPY 3.2-latest/ ./3.2-latest/
COPY Perl/ ./Perl/

WORKDIR /docker-scripts
RUN bash ./cqp_installation
ENV PATH "/usr/local/cwb-3.4.19:$PATH"
ENTRYPOINT ["bash", "./run_cqp"]
