const {
  withAppBuildGradle,
  withProjectBuildGradle,
} = require("@expo/config-plugins");

const GOOGLE_SERVICES_VERSION = "4.6.0";
const FIREBASE_BOM_VERSION = "35.2.0";

function withFirebaseAndroid(config) {
  config = withProjectBuildGradle(config, (gradleConfig) => {
    gradleConfig.modResults.contents = gradleConfig.modResults.contents.replace(
      /classpath ['"]com\.google\.gms:google-services:[^'"]+['"]/,
      `classpath 'com.google.gms:google-services:${GOOGLE_SERVICES_VERSION}'`,
    );
    return gradleConfig;
  });

  return withAppBuildGradle(config, (gradleConfig) => {
    const analyticsDependency = `implementation 'com.google.firebase:firebase-analytics'`;
    if (!gradleConfig.modResults.contents.includes(analyticsDependency)) {
      gradleConfig.modResults.contents = gradleConfig.modResults.contents.replace(
        'dependencies {',
        `dependencies {\n    implementation platform('com.google.firebase:firebase-bom:${FIREBASE_BOM_VERSION}')\n    ${analyticsDependency}`,
      );
    }
    return gradleConfig;
  });
}

module.exports = withFirebaseAndroid;