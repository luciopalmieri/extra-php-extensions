export BREF_VERSION=1.0.1
export php_versions=74

# other env variables available: PHP_BUILD_DIR, INSTALL_DIR


# build the docker images
make docker-images

# generate zip files of the layers in `export/` directory.
make layers

# make test

# register the zip file generated above to AWS as Lambda Layer. It also able to add from AWS console.
# aws lambda publish-layer-version --layer-name phash-php-74 --zip-file fileb://./export/layer-phash-php-74.zip