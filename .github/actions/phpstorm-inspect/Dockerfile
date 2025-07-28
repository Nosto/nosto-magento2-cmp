FROM supercid/jbstorm:latest
RUN apt-get update -y
RUN apt-get install -y xsltproc
COPY *.xslt /
COPY ../entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]