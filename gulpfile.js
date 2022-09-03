// Copyright 2020-2022 Markus Leupold-Löwenthal
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

import { readFileSync } from 'fs'
import { deleteAsync } from 'del'

import autoprefixer from 'gulp-autoprefixer'
import concat from 'gulp-concat'
import gulp from 'gulp'
import gzip from 'gulp-gzip'
import imagemin from 'gulp-imagemin'
import imageminPngquant from 'imagemin-pngquant'
import phpcs from 'gulp-phpcs'
import phplint from 'gulp-phplint'
import replace from 'gulp-replace'
import sassLint from 'gulp-sass-lint'
import sort from 'gulp-sort'
import tar from 'gulp-tar'
import zip from 'gulp-zip'

import dartSass from 'sass'
import gulpSass from 'gulp-sass'
const sass = gulpSass(dartSass)

const p = JSON.parse(readFileSync('./package.json'))

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
  return gulp.src(['src/themes/**/*.s+(a|c)ss'])
    .pipe(sassLint({ configFile: '.sass-lint.yml' }))
    .pipe(sassLint.format())
    .pipe(sassLint.failOnError())
})

gulp.task('test-core-php', function () {
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
    .pipe(phpcs.reporter('fail'))
})

gulp.task('test-theme-elegant-php', function () {
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
    .pipe(phpcs.reporter('fail'))
})

gulp.task('test-plugin-media-php', function () {
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
    .pipe(phpcs.reporter('fail'))
})

gulp.task('test-plugin-macro-php', function () {
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
    .pipe(phpcs.reporter('fail'))
})

gulp.task('test-plugin-user-php', function () {
  return gulp.src([
    'src/plugins/user/**/*php'
  ])
    .pipe(phplint('', { skipPassedFiles: true }))
    .pipe(phpcs({
      bin: 'tools/phpcs.phar',
      standard: 'PSR12',
      colors: 1,
      warningSeverity: 0
    }))
    .pipe(phpcs.reporter('log'))
    .pipe(phpcs.reporter('fail'))
})

gulp.task('tests-php', gulp.parallel('test-core-php', 'test-plugin-media-php', 'test-plugin-macro-php', 'test-plugin-user-php', 'test-theme-elegant-php'))
gulp.task('tests-sass', gulp.parallel('test-theme-elegant-sass'))
gulp.task('test', gulp.parallel('tests-sass', 'tests-php'))

gulp.task('clean', async function () {
  return await deleteAsync([
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

gulp.task('theme-elegant-scss', function () {
  return gulp.src([
    'src/themes/elegant/scss/main.scss'
    // include additional vendor-css from /node_modules here
  ])
    .pipe(concat('style.css'))
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(sass({ outputStyle: 'compressed' }))
    .pipe(autoprefixer())
    .pipe(gulp.dest(dirs.theme))
})

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

gulp.task('plugin-media-php', function () {
  return gulp.src([
    'src/plugins/media/**/*php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.plugins + '/media'))
})

gulp.task('plugin-media', gulp.parallel('plugin-media-php'))

// --- plugin: macro -----------------------------------------------------------

gulp.task('plugin-macro-php', function () {
  return gulp.src([
    'src/plugins/macro/**/*php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.plugins + '/macro'))
})

gulp.task('plugin-macro', gulp.parallel('plugin-macro-php'))

// --- plugin: user ------------------------------------------------------------

gulp.task('plugin-user-php', function () {
  return gulp.src([
    'src/plugins/user/**/*php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.plugins + '/user'))
})

gulp.task('plugin-user', gulp.parallel('plugin-user-php'))

// --- core --------------------------------------------------------------------

gulp.task('core-meta', function () {
  return gulp.src([
    'src/core/robots.txt',
    'src/core/.htaccess',
    'src/core/.htaccess-full'
  ])
    .pipe(gulp.dest(dirs.site))
})

gulp.task('core-php', function () {
  return gulp.src([
    'src/core/php/**/*.php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.site))
})

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

gulp.task('dist', gulp.series('test', gulp.parallel('core-php', 'core-meta', 'theme-elegant', 'plugin-media', 'plugin-macro', 'plugin-user', 'data'), 'docs'))

gulp.task('package-tgz', function () {
  return gulp.src([
    dirs.build + '/wiki.md/**/*'
  ], { base: dirs.build, dot: true })
    .pipe(sort())
    .pipe(tar('wiki.md-' + p.version + '.tar'))
    .pipe(gzip({ gzipOptions: { level: 9 } }))
    .pipe(gulp.dest(dirs.build))
})

gulp.task('package-zip', function () {
  return gulp.src([
    dirs.build + '/wiki.md/**/*'
  ], { base: dirs.build, dot: true })
    .pipe(sort())
    .pipe(zip('wiki.md-' + p.version + '.zip'))
    .pipe(gulp.dest(dirs.build))
})

gulp.task('release', gulp.series('clean', 'dist', 'package-tgz', 'package-zip'))
