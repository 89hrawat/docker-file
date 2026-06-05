==================================================
NGINX PHP REDIS project on redis 
===========================================================
step 1. create a saprate network 
        # docker network create app-net

step 2. Build the ngix alpine image (docker file of nginx)
        -----------------------------------------------------
FROM nginx:1.29-alpine
RUN apk update && \  ### this step req. for update apline image for barnavility 
    apk upgrade && \
    apk add --no-cache \
    curl \
    wget \
    bind-tools \
    iputils \
    net-tools \
    busybox-extras \
    openssl \
    tzdata
RUN mkdir -p /var/www/html
WORKDIR /var/www/html
COPY index.php  /var/www/html
RUN rm -f /etc/nginx/conf.d/default.conf
COPY nginx.conf /etc/nginx/nginx.conf
COPY default.conf /etc/nginx/conf.d/default.conf
EXPOSE 80 443
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]

note: nginx default and nginx configuration file.

step 3. build php-fpm alpine image 	
        --------------------------
FROM php:8.3-fpm-alpine
RUN apk update && \
    apk upgrade && \
	apk add --no-cache tzdata
	
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    curl \
    wget \
    bind-tools \
    iputils \
    net-tools \
    busybox-extras \
    bash
  

RUN docker-php-ext-install \  ### mysqli and pdo pdo mysql extaion installation 
    mysqli \
    pdo \
    pdo_mysql \
    opcache
# Install Redis PHP extension       
RUN pecl install redis && \
    docker-php-ext-enable redis

WORKDIR /var/www/html
COPY index.php .

EXPOSE 9000

CMD ["php-fpm","-F"]
------------------------------------------------------------------
php-fpm image optimize format
--------------------------
FROM php:8.3-fpm-alpine

RUN apk update && \
    apk upgrade && \
    apk add --no-cache \
        tzdata \
        curl \
        wget \
        bind-tools \
        iputils \
        net-tools \
        busybox-extras \
        bash

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS && \
    docker-php-ext-install mysqli pdo pdo_mysql opcache && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del .build-deps

WORKDIR /var/www/html

COPY index.php .

CMD ["php-fpm","-F"]

 
note: index.php  file reqired alos if req. customize php.ini and www.conf file shoud be availabe. 
step 4: build the redis master readonly image
        --------------------------------------
FROM redis:8-alpine
COPY redis-master.conf /usr/local/etc/redis/redis.conf
EXPOSE 6379
CMD ["redis-server", "/usr/local/etc/redis/redis.conf"]

step 5. redis mster configuration file must be available. withname redis-master.conf
step 6. build the image of nignx php-fpm and redis image 
        # docker build -t nginx-with-php.v1 .
		# docker build -t php-fpm-cstm:v1.redis .
		# docker build -t redis-master .
		# docker build -t redis-slave .
step 7. create nginx---> php-fpm---->redis container by command 
       #docker  container run --network app-net -d --name nginx -p 8088:80 nginx-with-php:latest
	   #docker container run --network app-net --name php-fpm -d  php-fpm-cstm:v2-redis
	   #docker run -d   --name redis-mster   --network app-net   redis-master:latest
	   #docker run -d   --name redis-slave   --network app-net   redis-master:latest
