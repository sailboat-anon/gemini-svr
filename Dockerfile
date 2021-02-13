FROM php:8.0.2-cli-alpine
RUN apk add git
RUN git clone git://github.com/sailboat-anon/gemini-svr.git
RUN ["php", "/gemini-svr/gemini-svr.php", "password"]