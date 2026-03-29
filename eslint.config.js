import neostandard from 'neostandard'

export default neostandard({
  ts: true,
  ignores: [
    'dist/**/*',
    'node_modules/**/*',
    'src/html/*.js'
  ]
})
