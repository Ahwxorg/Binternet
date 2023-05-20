echo Version?
read -r VERSION
docker build -t pinternet:$VERSION . && docker tag pinternet:$VERSION ahwx/pinternet:$VERSION && docker push ahwx/pinternet:$VERSION

docker build -t pinternet:latest . && docker tag pinternet:latest ahwx/pinternet:latest && docker push ahwx/pinternet:latest
