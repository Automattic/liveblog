const path = require('path')
const resolve = require('resolve')

exports.interfaceVersion = 2
exports.resolve = function (importPath, caller, config) {
  if (importPath === '' || !config.altSourceDir) {
    return { found: false }
  }
  const altSourceDir = path.resolve(config.altSourceDir)
  const callerInfo = path.parse(caller)
  const newLoc = callerInfo.dir.replace(altSourceDir, path.resolve(config.src)) + '/' + importPath
  try {
    const r = resolve.sync(newLoc, {})
    return { found: true, path: r }
  } catch (err) {
    return { found: false }
  }
}
