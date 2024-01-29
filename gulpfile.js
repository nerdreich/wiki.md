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
import replace from 'gulp-replace'
import sort from 'gulp-sort'
import tar from 'gulp-tar'
import zip from 'gulp-zip'

import * as dartSass from 'sass'
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

gulp.task('clean', async () => {
  return await deleteAsync([
    dirs.site + '/**/*',
    dirs.site + '/**/.*',
    dirs.build + '/*.gz',
    dirs.build + '/*.zip'
  ])
})

// --- theme: elegant ----------------------------------------------------------

gulp.task('theme-elegant-fonts', () => {
  return gulp.src([
    'src/themes/elegant/fonts/*/*woff',
    'src/themes/elegant/fonts/*/*woff2'
  ])
    .pipe(gulp.dest(dirs.theme + '/fonts/'))
})

gulp.task('theme-elegant-scss', () => {
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

gulp.task('theme-elegant-php', () => {
  return gulp.src([
    'src/themes/elegant/**/*.php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.theme))
})

gulp.task('theme-elegant-I18N', () => {
  return gulp.src([
    'src/themes/elegant/I18N/**/*'
  ])
    .pipe(gulp.dest(dirs.theme + '/I18N'))
})

gulp.task('theme-elegant-favicon', () => {
  return gulp.src([
    'src/themes/elegant/favicon/**/*'
  ])
    .pipe(replace('$NAME$', p.name, { skipBinary: true }))
    .pipe(replace('$BGCOLOR$', p.bgColor, { skipBinary: true }))
    .pipe(gulp.dest(dirs.theme))
})

gulp.task('theme-elegant', gulp.parallel('theme-elegant-fonts', 'theme-elegant-scss', 'theme-elegant-php', 'theme-elegant-I18N', 'theme-elegant-favicon'))

// --- plugin: media -----------------------------------------------------------

gulp.task('plugin-media-php', () => {
  return gulp.src([
    'src/plugins/media/**/*php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.plugins + '/media'))
})

gulp.task('plugin-media', gulp.parallel('plugin-media-php'))

// --- plugin: macro -----------------------------------------------------------

gulp.task('plugin-macro-php', () => {
  return gulp.src([
    'src/plugins/macro/**/*php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.plugins + '/macro'))
})

gulp.task('plugin-macro', gulp.parallel('plugin-macro-php'))

// --- plugin: user ------------------------------------------------------------

gulp.task('plugin-user-php', () => {
  return gulp.src([
    'src/plugins/user/**/*php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.plugins + '/user'))
})

gulp.task('plugin-user', gulp.parallel('plugin-user-php'))

// --- core --------------------------------------------------------------------

gulp.task('core-meta', () => {
  return gulp.src([
    'src/core/robots.txt',
    'src/core/.htaccess',
    'src/core/.htaccess-full'
  ])
    .pipe(gulp.dest(dirs.site))
})

gulp.task('core-php', () => {
  return gulp.src([
    'src/core/php/**/*.php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.site))
})

gulp.task('data', () => {
  return gulp.src([
    'data/**/*',
    'data/**/*'
  ], { dot: true })
    .pipe(gulp.dest(dirs.data))
})

gulp.task('docs', gulp.series(() => {
  return gulp.src([
    'docs/**/*.md'
  ])
    .pipe(replace('.md)', ')', { skipBinary: true })) // wiki.md does not use extensions
    .pipe(gulp.dest(dirs.data + '/content/docs'))
}, () => {
  return gulp.src([
    'docs/**/*.png'
  ])
    .pipe(gulp.dest(dirs.data + '/content/docs/_media'))
}))

gulp.task('dist', gulp.parallel('core-php', 'core-meta', 'theme-elegant', 'plugin-media', 'plugin-macro', 'plugin-user', 'data', 'docs'))

gulp.task('package-tgz', () => {
  return gulp.src([
    dirs.build + '/wiki.md/**/*'
  ], { base: dirs.build, dot: true })
    .pipe(sort())
    .pipe(tar('wiki.md-' + p.version + '.tar'))
    .pipe(gzip({ gzipOptions: { level: 9 } }))
    .pipe(gulp.dest(dirs.build))
})

gulp.task('package-zip', () => {
  return gulp.src([
    dirs.build + '/wiki.md/**/*'
  ], { base: dirs.build, dot: true })
    .pipe(sort())
    .pipe(zip('wiki.md-' + p.version + '.zip'))
    .pipe(gulp.dest(dirs.build))
})

gulp.task('package', gulp.series('clean', 'dist', 'package-tgz', 'package-zip'))
