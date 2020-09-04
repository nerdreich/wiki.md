// Copyright 2020 Markus Leupold-Löwenthal
//
// This file is part of wiki.md.
//
// wiki.md is free software: you can redistribute it and/or modify it under the
// terms of the GNU Affero General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option) any
// later version.
//
// wiki.md is distributed in the hope that it will be useful, but WITHOUT ANY
// WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
// A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with wiki.md. If not, see <https://www.gnu.org/licenses/>.

const p = require('./package.json')

const gulp = require('gulp')
const replace = require('gulp-replace')

const subdir = ''
// const subdir = '/mywiki'

const dirs = {
  build: 'dist/',
  site: 'dist/' + p.name + subdir,
  theme: 'dist/' + p.name + subdir + '/themes/elegant',
  plugins: 'dist/' + p.name + subdir + '/plugins',
  data: 'dist/' + p.name + subdir + '/data'
}

// --- testing targets ---------------------------------------------------

gulp.task('test-theme-elegant-sass', function () {
  const sassLint = require('gulp-sass-lint')
  return gulp.src(['src/themes/**/*.s+(a|c)ss'])
    .pipe(sassLint({ configFile: '.sass-lint.yml' }))
    .pipe(sassLint.format())
    .pipe(sassLint.failOnError())
})

gulp.task('test-core-php', function () {
  const phpcs = require('gulp-phpcs')
  const phplint = require('gulp-phplint')

  return gulp.src([
    'src/core/php/*.php',
    'src/core/php/core/*php'
  ])
    .pipe(phplint('', { skipPassedFiles: true }))
    .pipe(phpcs({
      bin: 'tools/phpcs.phar',
      standard: 'PSR12',
      colors: 1,
      warningSeverity: 0
    }))
    .pipe(phpcs.reporter('log'))
})

gulp.task('test-theme-elegant-php', function () {
  const phpcs = require('gulp-phpcs')
  const phplint = require('gulp-phplint')

  return gulp.src([
    'src/themes/**/*php'
  ])
    .pipe(phplint('', { skipPassedFiles: true }))
    .pipe(phpcs({
      bin: 'tools/phpcs.phar',
      standard: 'PSR12',
      colors: 1,
      warningSeverity: 0
    }))
    .pipe(phpcs.reporter('log'))
})

gulp.task('test-plugin-media-php', function () {
  const phpcs = require('gulp-phpcs')
  const phplint = require('gulp-phplint')

  return gulp.src([
    'src/plugins/media/**/*php'
  ])
    .pipe(phplint('', { skipPassedFiles: true }))
    .pipe(phpcs({
      bin: 'tools/phpcs.phar',
      standard: 'PSR12',
      colors: 1,
      warningSeverity: 0
    }))
    .pipe(phpcs.reporter('log'))
})

gulp.task('test-plugin-macro-php', function () {
  const phpcs = require('gulp-phpcs')
  const phplint = require('gulp-phplint')

  return gulp.src([
    'src/plugins/macro/**/*php'
  ])
    .pipe(phplint('', { skipPassedFiles: true }))
    .pipe(phpcs({
      bin: 'tools/phpcs.phar',
      standard: 'PSR12',
      colors: 1,
      warningSeverity: 0
    }))
    .pipe(phpcs.reporter('log'))
})

gulp.task('tests', gulp.series('test-theme-elegant-sass', 'test-core-php', 'test-plugin-media-php', 'test-plugin-macro-php', 'test-theme-elegant-php'))

gulp.task('clean', function () {
  const del = require('del')
  return del([
    dirs.site + '/**/*',
    dirs.site + '/**/.*',
    dirs.build + '/*.gz',
    dirs.build + '/*.zip'
  ])
})

// --- theme: elegant ----------------------------------------------------------

gulp.task('theme-elegant-fonts', function () {
  return gulp.src([
    'src/themes/elegant/fonts/*/*woff',
    'src/themes/elegant/fonts/*/*woff2'
  ])
    .pipe(gulp.dest(dirs.theme + '/fonts/'))
})

gulp.task('theme-elegant-scss', gulp.series('test-theme-elegant-sass', function () {
  const sass = require('gulp-sass')
  const concat = require('gulp-concat')
  const autoprefixer = require('gulp-autoprefixer')

  return gulp.src([
    'src/themes/elegant/scss/main.scss'
    // include additional vendor-css from /node_modules here
  ])
    .pipe(concat('style.css'))
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(sass({ outputStyle: 'compressed' }))
    .pipe(autoprefixer())
    .pipe(gulp.dest(dirs.theme))
}))

gulp.task('theme-elegant-php', gulp.series('test-theme-elegant-php', function () {
  return gulp.src([
    'src/themes/elegant/**/*.php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.theme))
}))

gulp.task('theme-elegant-I18N', function () {
  return gulp.src([
    'src/themes/elegant/I18N/**/*'
  ])
    .pipe(gulp.dest(dirs.theme + '/I18N'))
})

gulp.task('theme-elegant-favicon', function () {
  const imagemin = require('gulp-imagemin')
  const imageminPngquant = require('imagemin-pngquant')

  return gulp.src([
    'src/themes/elegant/favicon/**/*'
  ])
    .pipe(imagemin([
      imageminPngquant({ quality: [0.8, 0.9], strip: true })
    ], { verbose: true }))
    .pipe(replace('$NAME$', p.name, { skipBinary: true }))
    .pipe(replace('$BGCOLOR$', p.bgColor, { skipBinary: true }))
    .pipe(gulp.dest(dirs.theme))
})

gulp.task('theme-elegant', gulp.parallel('theme-elegant-fonts', 'theme-elegant-scss', 'theme-elegant-php', 'theme-elegant-I18N', 'theme-elegant-favicon'))

// --- plugin: media -----------------------------------------------------------

gulp.task('plugin-media-php', gulp.series('test-plugin-media-php', function () {
  return gulp.src([
    'src/plugins/media/**/*php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.plugins + '/media'))
}))

gulp.task('plugin-media', gulp.parallel('plugin-media-php'))

// --- plugin: macro -----------------------------------------------------------

gulp.task('plugin-macro-php', gulp.series('test-plugin-macro-php', function () {
  return gulp.src([
    'src/plugins/macro/**/*php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.plugins + '/macro'))
}))

gulp.task('plugin-macro', gulp.parallel('plugin-macro-php'))

// --- core --------------------------------------------------------------------

gulp.task('core-meta', function () {
  return gulp.src([
    'src/core/robots.txt',
    'src/core/.htaccess',
    'src/core/.htaccess-full'
  ])
    .pipe(gulp.dest(dirs.site))
})

gulp.task('core-php', gulp.series('test-core-php', function () {
  return gulp.src([
    'src/core/php/**/*.php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.site))
}))

gulp.task('data', function () {
  return gulp.src([
    'data/**/*',
    'data/**/*'
  ], { dot: true })
    .pipe(gulp.dest(dirs.data))
})

gulp.task('docs', gulp.series(function () {
  return gulp.src([
    'docs/**/*.md'
  ])
    .pipe(replace('.md)', ')', { skipBinary: true })) // wiki.md does not use extensions
    .pipe(gulp.dest(dirs.data + '/content/docs'))
}, function () {
  return gulp.src([
    'docs/**/*.png'
  ])
    .pipe(gulp.dest(dirs.data + '/content/docs/_media'))
}))

gulp.task('dist', gulp.series(gulp.parallel('core-php', 'core-meta', 'theme-elegant', 'plugin-media', 'plugin-macro', 'data'), 'docs'))

gulp.task('package-tgz', function () {
  const tar = require('gulp-tar')
  const gzip = require('gulp-gzip')
  const sort = require('gulp-sort')

  return gulp.src([
    dirs.build + '/wiki.md/**/*'
  ], { base: dirs.build, dot: true })
    .pipe(sort())
    .pipe(tar('wiki.md-' + p.version + '.tar'))
    .pipe(gzip({ gzipOptions: { level: 9 } }))
    .pipe(gulp.dest(dirs.build))
})

gulp.task('package-zip', function () {
  const zip = require('gulp-zip')
  const sort = require('gulp-sort')

  return gulp.src([
    dirs.build + '/wiki.md/**/*'
  ], { base: dirs.build, dot: true })
    .pipe(sort())
    .pipe(zip('wiki.md-' + p.version + '.zip'))
    .pipe(gulp.dest(dirs.build))
})

gulp.task('release', gulp.series('clean', 'dist', 'package-tgz', 'package-zip'))
