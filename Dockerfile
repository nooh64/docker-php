FROM ubuntu
MAINTAINER Nooh KVM <nooh64@gmail.com>
RUN apt-get update -y
RUN apt-get install -y apache2
RUN apt-get install -y mysql-client mysql-server
RUN apt-get install -y php7.0
