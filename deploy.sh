#!/bin/bash
set -e

docker pull quay.io/keboola/developer-portal-cli-v2:latest
export REPOSITORY=`docker run --rm  \
    -e KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD  \
    quay.io/keboola/developer-portal-cli-v2:latest ecr:get-repository keboola keboola.processor-create-manifest`
docker tag keboola/processor-create-manifest:latest ${REPOSITORY}:${TRAVIS_TAG}
docker tag keboola/processor-create-manifest:latest ${REPOSITORY}:latest
eval $(docker run --rm \
    -e KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD -e KBC_DEVELOPERPORTAL_URL \
    quay.io/keboola/developer-portal-cli-v2:latest ecr:get-login keboola keboola.processor-create-manifest)
docker push ${REPOSITORY}:${TRAVIS_TAG}
docker push ${REPOSITORY}:latest

# Deploy to KBC -> update the tag in Keboola Developer Portal (needs $KBC_DEVELOPERPORTAL_VENDOR & $KBC_DEVELOPERPORTAL_APP)
docker run --rm -e KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD -e KBC_DEVELOPERPORTAL_URL \
    quay.io/keboola/developer-portal-cli-v2:latest update-app-repository keboola keboola.processor-create-manifest ${TRAVIS_TAG} ecr ${REPOSITORY}
