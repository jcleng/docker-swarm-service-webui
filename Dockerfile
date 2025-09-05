FROM registry.cn-hangzhou.aliyuncs.com/jcleng/gitbuild-php:8.1-cli
ENV DEBIAN_FRONTEND=noninteractive
RUN sed -i 's/archive.ubuntu.com/mirrors.ustc.edu.cn/g' /etc/apt/sources.list.d/debian.sources
RUN mkdir -p /src
WORKDIR /src
COPY ./src /src
ENV AUTHORIZATION=admin888

RUN composer install

CMD ["php", "-S", "0.0.0.0:80"]
