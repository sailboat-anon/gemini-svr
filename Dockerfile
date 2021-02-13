FROM php:8.0.2-cli-alpine
RUN apk add git
RUN git clone git://github.com/sailboat-anon/gemini-svr.git
WORKDIR ~/gemini-svr/
COPY ./certs/sailboat-anon.space/sailboat-anon.space.pem certs/sailboat-anon.space.pem
RUN ["php", "~/gemini-svr/gemini-svr.php", "password"]