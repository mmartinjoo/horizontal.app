deploy:
	docker buildx build --push --platform linux/amd64 --provenance=false -f Dockerfile.prod -t 325384049248.dkr.ecr.us-east-1.amazonaws.com/horizontal/graphbuilder:latest .
	serverless deploy --debug 2>&1 | tee deploy.log
