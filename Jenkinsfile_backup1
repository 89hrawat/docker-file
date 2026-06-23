pipeline {

agent {
    label 'docker-12'
}

environment {

    NEXUS_URL = "nexus.example.com:13002"

    REDIS_MASTER_IMAGE = "${NEXUS_URL}/custom/redis-master:${BUILD_NUMBER}"
    REDIS_SLAVE_IMAGE  = "${NEXUS_URL}/custom/redis-slave:${BUILD_NUMBER}"
    PHP_IMAGE          = "${NEXUS_URL}/custom/php-fpm:${BUILD_NUMBER}"
    NGINX_IMAGE        = "${NEXUS_URL}/custom/nginx:${BUILD_NUMBER}"

    NEXUS_REPO        = "custom"   // Your Nexus repo name
    KEEP_IMAGES       = "4"        // How many latest images to keep
}

stages {

    stage('Checkout Source') {
        steps {
            git(
                branch: 'main',
                credentialsId: 'devops-12',
                url: 'git@github.com:89hrawat/docker-file.git'
            )
        }
    }

    stage('Build Redis Master Image') {
        steps {
            sh '''
            docker build \
            -t ${REDIS_MASTER_IMAGE} \
            ./redis_M
            '''
        }
    }

    stage('Build Redis Slave Image') {
        steps {
            sh '''
            docker build \
            -t ${REDIS_SLAVE_IMAGE} \
            ./redis-slave
            '''
        }
    }

    stage('Build PHP-FPM Image') {
        steps {
            sh '''
            docker build \
            -t ${PHP_IMAGE} \
            ./php-fpm
            '''
        }
    }

    stage('Build NGINX Image') {
        steps {
            sh '''
            docker build \
            -t ${NGINX_IMAGE} \
            ./nginx
            '''
        }
    }

    stage('Generate Trivy Reports') {
        steps {
            sh '''
            trivy image -f json -o redis-master-report.json ${REDIS_MASTER_IMAGE}
            trivy image -f json -o redis-slave-report.json  ${REDIS_SLAVE_IMAGE}
            trivy image -f json -o php-report.json          ${PHP_IMAGE}
            trivy image -f json -o nginx-report.json        ${NGINX_IMAGE}
            '''
        }
    }

    stage('Trivy Security Gate') {
        steps {
            sh '''
            trivy image --severity HIGH,CRITICAL --exit-code 1 ${REDIS_MASTER_IMAGE}
            trivy image --severity HIGH,CRITICAL --exit-code 1 ${REDIS_SLAVE_IMAGE}
            trivy image --severity HIGH,CRITICAL --exit-code 1 ${PHP_IMAGE}
            trivy image --severity HIGH,CRITICAL --exit-code 1 ${NGINX_IMAGE}
            '''
        }
    }

    stage('Docker Login Nexus') {
        steps {
            withCredentials([
                usernamePassword(
                    credentialsId: 'nexus-creds',
                    usernameVariable: 'NEXUS_USER',
                    passwordVariable: 'NEXUS_PASS'
                )
            ]) {
                sh '''
                echo "$NEXUS_PASS" | docker login \
                ${NEXUS_URL} \
                -u "$NEXUS_USER" \
                --password-stdin
                '''
            }
        }
    }

    stage('Push Images To Nexus') {
        steps {
            sh '''
            docker push ${REDIS_MASTER_IMAGE}
            docker push ${REDIS_SLAVE_IMAGE}
            docker push ${PHP_IMAGE}
            docker push ${NGINX_IMAGE}
            '''
        }
    }

    stage('Deploy Containers') {
        steps {
            sh '''
            docker network create app-net || true

            docker rm -f nginx         || true
            docker rm -f php-fpm       || true
            docker rm -f redis-master  || true
            docker rm -f redis-slave   || true

            docker run -d --name redis-master --network app-net ${REDIS_MASTER_IMAGE}

            sleep 10

            docker run -d --name redis-slave  --network app-net ${REDIS_SLAVE_IMAGE}
            docker run -d --name php-fpm      --network app-net ${PHP_IMAGE}
            docker run -d --name nginx        --network app-net -p 8088:80 ${NGINX_IMAGE}
            '''
        }
    }

    stage('Wait For Startup') {
        steps {
            sh 'sleep 20'
        }
    }

    stage('Verify Redis Master') {
        steps {
            sh 'docker exec redis-master redis-cli ping | grep PONG'
        }
    }

    stage('Verify Redis Replica') {
        steps {
            sh 'docker exec redis-slave redis-cli info replication | grep role:slave'
        }
    }

    stage('Verify PHP-FPM') {
        steps {
            sh '''
            docker exec php-fpm pgrep php-fpm
            docker exec php-fpm php -m | grep redis
            '''
        }
    }

    stage('Verify NGINX Config') {
        steps {
            sh 'docker exec nginx nginx -t'
        }
    }

    stage('Verify NGINX Service') {
        steps {
            sh 'curl -f http://localhost:8088'
        }
    }

    stage('Container Status') {
        steps {
            sh 'docker ps'
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // NEXUS CLEANUP — Delete old images, keep only latest 4 per image
    // Uses Nexus REST API with component ID (no docker command needed)
    // ─────────────────────────────────────────────────────────────────
    stage('Cleanup Old Nexus Images') {
        steps {
            withCredentials([
                usernamePassword(
                    credentialsId: 'nexus-creds',
                    usernameVariable: 'NEXUS_USER',
                    passwordVariable: 'NEXUS_PASS'
                )
            ]) {
                sh '''
#!/bin/bash
set -e

NEXUS_BASE="https://nexus.example.com:13002"
REPO="${NEXUS_REPO}"
KEEP=${KEEP_IMAGES}

# All 4 image names to clean up
IMAGES="redis-master redis-slave php-fpm nginx"

cleanup_image() {
    IMAGE_NAME=$1
    echo ""
    echo "=========================================="
    echo "  Cleaning up: ${REPO}/${IMAGE_NAME}"
    echo "=========================================="

    CONTINUATION_TOKEN=""
    ALL_IDS=""
    ALL_VERSIONS=""

    # ── Fetch ALL pages from Nexus (handles pagination) ──
    while true; do

        if [ -z "$CONTINUATION_TOKEN" ]; then
            URL="${NEXUS_BASE}/service/rest/v1/search?repository=${REPO}&name=${IMAGE_NAME}&sort=version&direction=desc"
        else
            URL="${NEXUS_BASE}/service/rest/v1/search?repository=${REPO}&name=${IMAGE_NAME}&sort=version&direction=desc&continuationToken=${CONTINUATION_TOKEN}"
        fi

        RESPONSE=$(curl -s -u "${NEXUS_USER}:${NEXUS_PASS}" "$URL")

        # Extract IDs and versions from this page
        PAGE_IDS=$(echo "$RESPONSE"     | jq -r '.items[].id')
        PAGE_VERSIONS=$(echo "$RESPONSE" | jq -r '.items[].version')

        ALL_IDS=$(printf "%s\n%s" "$ALL_IDS" "$PAGE_IDS")
        ALL_VERSIONS=$(printf "%s\n%s" "$ALL_VERSIONS" "$PAGE_VERSIONS")

        # Check if more pages exist
        CONTINUATION_TOKEN=$(echo "$RESPONSE" | jq -r '.continuationToken // empty')
        if [ -z "$CONTINUATION_TOKEN" ]; then
            break
        fi

        echo "  → Fetching next page..."
    done

    # Clean up blank lines
    ALL_IDS=$(echo "$ALL_IDS"         | grep -v '^$')
    ALL_VERSIONS=$(echo "$ALL_VERSIONS" | grep -v '^$')

    TOTAL=$(echo "$ALL_IDS" | wc -l)
    echo "  Found ${TOTAL} version(s) in Nexus"

    if [ "$TOTAL" -le "$KEEP" ]; then
        echo "  ✅ Only ${TOTAL} image(s). Nothing to delete."
        return
    fi

    DELETE_COUNT=$((TOTAL - KEEP))
    echo "  ✔ Keeping latest ${KEEP} versions"
    echo "  🗑 Will delete ${DELETE_COUNT} old version(s)"
    echo ""

    # ── Delete old images (skip first $KEEP lines) ──
    echo "$ALL_IDS" | tail -n +$((KEEP + 1)) | while read -r COMP_ID; do

        VERSION=$(echo "$ALL_VERSIONS" | sed -n "$((KEEP + 1))p")

        echo "  🗑 Deleting component ID: ${COMP_ID}"

        HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
            -X DELETE \
            -u "${NEXUS_USER}:${NEXUS_PASS}" \
            "${NEXUS_BASE}/service/rest/v1/components/${COMP_ID}")

        if [ "$HTTP_CODE" = "204" ]; then
            echo "  ✅ Deleted successfully (HTTP 204)"
        else
            echo "  ❌ Failed to delete (HTTP ${HTTP_CODE})"
        fi
    done
}

# ── Run cleanup for all 4 images ──
for IMG in $IMAGES; do
    cleanup_image "$IMG"
done

echo ""
echo "=========================================="
echo "  Nexus Cleanup Complete!"
echo "=========================================="
                '''
            }
        }
    }
}

post {

    always {
        archiveArtifacts(
            artifacts: '*.json',
            fingerprint: true
        )
    }

    success {
        echo "=================================="
        echo "BUILD SUCCESSFUL"
        echo "Images pushed to Nexus"
        echo "Containers deployed successfully"
        echo "Old Nexus images cleaned up"
        echo "=================================="
    }

    failure {
        echo "=================================="
        echo "BUILD FAILED"
        echo "Check Trivy Reports / Logs"
        echo "=================================="
    }
}

}
