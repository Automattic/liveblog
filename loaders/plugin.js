const util = require('util')
const fs = require('fs')
const path = require('path')

class ComponentsOverwriter {
  constructor (altSourceDir) {
    this.altSourceDir = altSourceDir ? path.resolve(altSourceDir) : null
    this.modifyContext = this.modifyContext.bind(this)
  }

  modifyContext (result, callback) {
    if (this.altSourceDir && result) {

      /** If we have an issuer in our alternative directory then check request path
       * and optionally fallback to original source.
       */
      if (result.contextInfo && result.contextInfo.issuer && result.contextInfo.issuer.startsWith(this.altSourceDir)) {
        const fCheck = result.context + '/' + result.request + '.js'
        if (!fs.existsSync(fCheck)) {
          result.context = result.context.replace(this.altSourceDir, this.context)
        }
      } else {
        /**
         * Check alternative source directory for components.
         */
        const newContext = result.context.replace(this.context, this.altSourceDir)
        const fCheck = newContext + '/' + result.request + '.js'
        if (newContext !== result.context && fs.existsSync(fCheck)) {
          result.context = newContext
        }
      }
    }
    return callback(null, result)
  }

  apply (compiler) {
    compiler.plugin('normal-module-factory', (nmf) => {
      this.context = nmf.context
      nmf.plugin('before-resolve', this.modifyContext)
    })
  }
}

module.exports = ComponentsOverwriter
