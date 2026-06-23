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

            trivy image \
            -f json \
            -o redis-master-report.json \
            ${REDIS_MASTER_IMAGE}

            trivy image \
            -f json \
            -o redis-slave-report.json \
            ${REDIS_SLAVE_IMAGE}

            trivy image \
            -f json \
            -o php-report.json \
            ${PHP_IMAGE}

            trivy image \
            -f json \
            -o nginx-report.json \
            ${NGINX_IMAGE}

            '''
        }
    }

    stage('Trivy Security Gate') {

        steps {

            sh '''

            trivy image \
            --severity HIGH,CRITICAL \
            --exit-code 1 \
            ${REDIS_MASTER_IMAGE}

            trivy image \
            --severity HIGH,CRITICAL \
            --exit-code 1 \
            ${REDIS_SLAVE_IMAGE}

            trivy image \
            --severity HIGH,CRITICAL \
            --exit-code 1 \
            ${PHP_IMAGE}

            trivy image \
            --severity HIGH,CRITICAL \
            --exit-code 1 \
            ${NGINX_IMAGE}

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

            docker rm -f nginx || true
            docker rm -f php-fpm || true
            docker rm -f redis-master || true
            docker rm -f redis-slave || true

            docker run -d \
            --name redis-master \
            --network app-net \
            ${REDIS_MASTER_IMAGE}

            sleep 10

            docker run -d \
            --name redis-slave \
            --network app-net \
            ${REDIS_SLAVE_IMAGE}

            docker run -d \
            --name php-fpm \
            --network app-net \
            ${PHP_IMAGE}

            docker run -d \
            --name nginx \
            --network app-net \
            -p 8088:80 \
            ${NGINX_IMAGE}

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

            sh '''

            docker exec redis-master \
            redis-cli ping | grep PONG

            '''
        }
    }

    stage('Verify Redis Replica') {

        steps {

            sh '''

            docker exec redis-slave \
            redis-cli info replication \
            | grep role:slave

            '''
        }
    }

    stage('Verify PHP-FPM') {

        steps {

            sh '''

            docker exec php-fpm \
            pgrep php-fpm

            docker exec php-fpm \
            php -m | grep redis

            '''
        }
    }

    stage('Verify NGINX Config') {

        steps {

            sh '''

            docker exec nginx nginx -t

            '''
        }
    }

    stage('Verify NGINX Service') {

        steps {

            sh '''

            curl -f http://localhost:8088

            '''
        }
    }

    stage('Container Status') {

        steps {

            sh '''

            docker ps

            '''
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

