FROM alpine:3.20

RUN apk add php83 php83-fpm php83-dom php83-curl php83-json php83-openssl nginx --no-cache
RUN sed -i 's/user nginx;/user nobody;/' /etc/nginx/nginx.conf \
    && sed -i 's/listen = 127.0.0.1:9000/listen = \/run\/php-fpm83.sock/' /etc/php83/php-fpm.d/www.conf \
    && sed -i 's/;listen.owner/listen.owner/' /etc/php83/php-fpm.d/www.conf \
    && sed -i 's/;listen.group/listen.group/' /etc/php83/php-fpm.d/www.conf \
    && sed -i 's/;listen.mode/listen.mode/' /etc/php83/php-fpm.d/www.conf \
    && sed -i 's/;listen.allowed_clients/listen.allowed_clients/' /etc/php83/php-fpm.d/www.conf

RUN mkdir -p /var/www/binternet
COPY . /var/www/binternet
COPY nginx.conf /etc/nginx/http.d/binternet.conf
RUN rm /var/www/binternet/nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 80
ENTRYPOINT ["/bin/sh", "-c" , "/usr/sbin/php-fpm83 -D && /usr/sbin/nginx -c /etc/nginx/nginx.conf -g 'daemon off;'"]
