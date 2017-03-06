FROM httpd
MAINTAINER Nooh KVM <nooh.km@pitsolutions.com>
RUN apt-get update -y && apt-get install -y php7.0 && apt-get install -y libapache2-mod-php7.0 php7.0-mysql php7.0-curl php7.0-json
COPY ./typo3_src/ /usr/local/apache2/htdocs/