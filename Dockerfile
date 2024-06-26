FROM docker.io/rockylinux:9

# Docker image for webservice

# Install the necessary packages
RUN dnf install -y https://dl.rockylinux.org/pub/rocky/9/AppStream/x86_64/os/Packages/t/tesseract-4.1.1-7.el9.x86_64.rpm \
    https://dl.rockylinux.org/pub/rocky/9/AppStream/x86_64/os/Packages/l/leptonica-1.80.0-4.el9.1.x86_64.rpm \
    https://dl.rockylinux.org/pub/rocky/9/AppStream/x86_64/os/Packages/t/tesseract-langpack-eng-4.1.0-3.el9.noarch.rpm \
    https://dl.rockylinux.org/pub/rocky/9/AppStream/x86_64/os/Packages/t/tesseract-tessdata-doc-4.1.0-3.el9.noarch.rpm && \
    dnf install -y python3-pip && \
    dnf install -y https://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm https://rpms.remirepo.net/enterprise/remi-release-9.rpm && \
    crb enable && \
    dnf module reset php && \
    dnf module enable php:remi-8.2 -y && \
    dnf install -y php-gd && \
    dnf clean all

COPY requirements.txt /tmp/.

RUN pip install -r /tmp/requirements.txt

# Run php as a webserver on web/
WORKDIR /app
CMD ["php", "-S", "0.0.0.0:8123", "-t", "web/"]
