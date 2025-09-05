FROM registry.cn-hangzhou.aliyuncs.com/jcleng/library-php:8.5-rc-cli-alpine
# composer
COPY --from=registry.cn-hangzhou.aliyuncs.com/jcleng/library-composer:latest /usr/bin/composer /usr/bin/composer
#
ENV DEBIAN_FRONTEND=noninteractive
RUN mkdir -p /src
WORKDIR /src
COPY ./src /src
ENV AUTHORIZATION=admin888

RUN composer install --ignore-platform-reqs

CMD ["php", "-S", "0.0.0.0:80"]
