FROM nginx:1.29-alpine
RUN apk update && \
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
