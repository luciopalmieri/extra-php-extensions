ARG PHP_VERSION
ARG BREF_VERSION
FROM --platform=amd64 bref/build-php-$PHP_VERSION:$BREF_VERSION AS ext
ARG PHP_VERSION

# Prepare environment
ENV PHASH_BUILD_DIR=${BUILD_DIR}/phash
RUN mkdir -p ${PHASH_BUILD_DIR}
RUN LD_LIBRARY_PATH= yum -y install libpng-devel libjpeg-devel libtiff-devel

# Compile the php pHash library
WORKDIR ${PHASH_BUILD_DIR}
RUN git clone https://github.com/aetilius/pHash.git
WORKDIR ${PHASH_BUILD_DIR}/pHash
RUN git reset --hard dea9ffca729841db087f46a7389dd8610a629dc6
RUN cmake -DLIBDIR=lib -DWITH_AUDIO_HASH=OFF -DWITH_VIDEO_HASH=OFF -DWITH_IMAGE_HASH=ON -DPHASH_BINDINGS=ON -DPHASH_DYNAMIC=ON .
RUN make
RUN make install

# Compile the php pHash extension
WORKDIR ${PHASH_BUILD_DIR}/pHash
COPY dev-data/pHash.cpp.patch ./bindings/php
COPY dev-data/php_pHash.h.patch ./bindings/php
RUN cp third-party/CImg/CImg.h /usr/local/include
RUN cp src/*.h /usr/local/include
RUN cd bindings/php && phpize
RUN cd bindings/php && ./configure LIBS="-lpthread" --with-pHash="/usr/local"

# apply patch
RUN cd bindings/php && patch < ./pHash.cpp.patch
RUN cd bindings/php && patch < ./php_pHash.h.patch

# patch pHash.cpp
# RUN cd bindings/php && patch < ./patch-pHash.cpp
# RUN cd bindings/php && patch < ./patch-php__pHash.h


RUN cd bindings/php && make -j $(nproc)
RUN cd bindings/php && make install

RUN cp `php-config --extension-dir`/pHash.so /tmp/pHash.so
RUN strip --strip-debug /tmp/pHash.so
RUN echo 'extension=pHash.so' > /tmp/ext.ini

COPY ./utils/copy-dependencies.php .

RUN php ./copy-dependencies.php /tmp/pHash.so /tmp/extension-libs

# Build the final image with just the files we need
FROM scratch

# Copy things we installed to the final image
COPY --from=ext /tmp/pHash.so /opt/bref/extensions/pHash.so
COPY --from=ext /tmp/ext.ini /opt/bref/etc/php/conf.d/ext-phash.ini
COPY --from=ext /tmp/extension-libs /opt/lib
# COPY --from=ext /tmp/gs /opt/bin/gs
