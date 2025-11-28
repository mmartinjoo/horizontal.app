sync-secrets:
	kubectl annotate externalsecret app-secrets force-sync=$$(date +%s) --overwrite
	kubectl rollout restart deployment/api
	kubectl rollout restart deployment/worker-indexing
	kubectl rollout restart deployment/worker-question
	kubectl rollout restart deployment/worker-default
	kubectl rollout restart deployment/graphbuilder-api
	kubectl rollout restart deployment/graphbuilder-worker

logs-api:
	kubectl logs -l app=api --tail=100 -f

logs-worker-default:
	kubectl logs -l app=worker-default --tail=100 -f
