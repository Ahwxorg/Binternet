echo Version?
read -r VERSION
docker build -t binternet:$VERSION . && docker tag binternet:$VERSION ahwx/binternet:$VERSION && docker push ahwx/binternet:$VERSION

docker build -t binternet:latest . && docker tag binternet:latest ahwx/binternet:latest && docker push ahwx/binternet:latest
