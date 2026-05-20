function optionalModule(moduleName, installHint = null) {
  try {
    return require(moduleName);
  } catch (error) {
    if (error.code === 'MODULE_NOT_FOUND') {
      const hint = installHint || `npm install ${moduleName}`;
      throw new Error(`Missing dependency "${moduleName}". Install it before enabling this network: ${hint}`);
    }

    throw error;
  }
}

module.exports = optionalModule;
