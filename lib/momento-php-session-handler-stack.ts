import * as cdk from 'aws-cdk-lib';
import { Construct } from 'constructs';
import * as ec2 from "aws-cdk-lib/aws-ec2";
import * as ecs from 'aws-cdk-lib/aws-ecs';
import * as ecs_patterns from "aws-cdk-lib/aws-ecs-patterns";
import { DockerImageAsset } from 'aws-cdk-lib/aws-ecr-assets';
import * as path from 'path';
import { env } from 'process';

export class MomentoPhpSessionHandlerStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    const vpc = new ec2.Vpc(this, "MomentoPhpSessionHandlerVpc", {
      maxAzs: 2 // Default is all AZs in region
    });

    const cluster = new ecs.Cluster(this, "MomentoPhpSessionHandlerCluster", {
      vpc: vpc,
      clusterName: "MomentoPhpSessionHandler",
      enableFargateCapacityProviders: true,
    });

    const taskDefinition = new ecs.FargateTaskDefinition(this, 'TaskDef', {
      //ensure we use ECS fargate with ARM64
      runtimePlatform: {
        operatingSystemFamily: ecs.OperatingSystemFamily.LINUX,
        cpuArchitecture: ecs.CpuArchitecture.ARM64
      },
      
    });

    const nginxContainer = taskDefinition.addContainer('Nginx', {
      image: ecs.ContainerImage.fromDockerImageAsset(new DockerImageAsset(this, 'nginx', {
        directory: path.join(__dirname, '../app'),
        platform: cdk.aws_ecr_assets.Platform.LINUX_ARM64,
        target: 'nginx',
      })),
      essential: true,
      portMappings: [
        {
          containerPort: 80,
          hostPort: 80,
          protocol: ecs.Protocol.TCP
        }
      ]
    });

    const phpFpmContainer = taskDefinition.addContainer('PhpFpm', {
      image: ecs.ContainerImage.fromDockerImageAsset(new DockerImageAsset(this, 'PhpFpm', {
        directory: path.join(__dirname, '../app'),
        platform: cdk.aws_ecr_assets.Platform.LINUX_ARM64,
        target: 'fpm',
      })),
      essential: true,
      environment: {
        //retrieve momento API key from the current environment
        'MOMENTO_AUTH_TOKEN': env.MOMENTO_AUTH_TOKEN??'',
        'MONENTO_SESSION_CACHE': env.MONENTO_SESSION_CACHE??'php-sessions',
        'MOMENTO_SESSION_TTL': '120',
      },
      logging: new ecs.AwsLogDriver({
        streamPrefix: 'momento-php-fpm',
      }),
    });

    // Create a load-balanced Fargate spot service and make it public
    new ecs_patterns.ApplicationLoadBalancedFargateService(this, "MomentoPhpSessionHandlerService", {
      cluster: cluster, // Required
      desiredCount: 3, // Default is 1
      circuitBreaker: {
        rollback: true
      },
      taskDefinition,
      publicLoadBalancer: true, // Default is true
    });
  }
}
