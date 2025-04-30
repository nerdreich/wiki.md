import { defineConfig, globalIgnores } from 'eslint/config'
import globals from 'globals'

export default defineConfig([globalIgnores(['dist', 'node_modules']), {
  languageOptions: {
    globals: {
      ...globals.browser
    },

    ecmaVersion: 'latest',
    sourceType: 'module'
  },

  rules: {}
}])
