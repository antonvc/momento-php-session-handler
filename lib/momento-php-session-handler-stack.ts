import * as cdk from 'aws-cdk-lib';
import { Construct } from 'constructs';
import * as ec2 from "aws-cdk-lib/aws-ec2";
import * as ecs from 'aws-cdk-lib/aws-ecs';
import * as ecs_patterns from "aws-cdk-lib/aws-ecs-patterns";
import { DockerImageAsset } from 'aws-cdk-lib/aws-ecr-assets';
import * as path from 'path';

export class MomentoPhpSessionHandlerStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    const vpc = new ec2.Vpc(this, "MomentoPhpSessionHandlerVpc", {
      maxAzs: 2 // Default is all AZs in region
    });

    const cluster = new ecs.Cluster(this, "MomentoPhpSessionHandlerCluster", {
      vpc: vpc,
      clusterName: "MomentoPhpSessionHandlerCluster",
      enableFargateCapacityProviders: true,
    });

     const taskDefinition = new ecs.FargateTaskDefinition(this, 'TaskDef', {
      volumes: [
        { name: "www" },
      ]
     });

    const nginxContainer = taskDefinition.addContainer('Nginx', {
      image: ecs.ContainerImage.fromDockerImageAsset(new DockerImageAsset(this, 'nginx', {
        directory: path.join(__dirname, '../app/nginx'),
        platform: cdk.aws_ecr_assets.Platform.LINUX_AMD64,
      })),
      essential: true,
      portMappings: [
        {
          containerPort: 80,
          hostPort: 80,
          protocol: ecs.Protocol.TCP
        }
      ],
    });
    nginxContainer.addMountPoints({
      containerPath: "/var/www/html",
      sourceVolume: "www",
      readOnly: false
    });

    // const phpFpmContainer = taskDefinition.addContainer('PhpFpm', {
    //   image: ecs.ContainerImage.fromDockerImageAsset(new DockerImageAsset(this, 'PhpFpm', {
    //     directory: path.join(__dirname, '../app/php-fpm'),
    //     platform: cdk.aws_ecr_assets.Platform.LINUX_ARM64,
    //   })),
    //   essential: true,

    // });
    // phpFpmContainer.addMountPoints({
    //   containerPath: "/var/www/html",
    //   sourceVolume: "www",
    //   readOnly: false
    // });

    // Create a load-balanced Fargate spot service and make it public
    new ecs_patterns.ApplicationLoadBalancedFargateService(this, "MomentoPhpSessionHandlerService", {
      cluster: cluster, // Required
      cpu: 512, // Default is 256
      desiredCount: 1, // Default is 1
      taskDefinition,
      memoryLimitMiB: 2048, // Default is 512
      publicLoadBalancer: true, // Default is true
    });
  }
}
