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

const dirs = {
  build: 'dist/',
  site: 'dist/' + p.name,
  theme: 'dist/' + p.name + '/themes/elegant',
  data: 'dist/' + p.name + '/content'
}

// --- testing targets ---------------------------------------------------

gulp.task('test-sass', function () {
  const sassLint = require('gulp-sass-lint')
  return gulp.src(['src/**/*.s+(a|c)ss'])
    .pipe(sassLint({ configFile: '.sass-lint.yml' }))
    .pipe(sassLint.format())
    .pipe(sassLint.failOnError())
})

gulp.task('test-php', function () {
  const phpcs = require('gulp-phpcs')
  return gulp.src([
    'src/php/*.php',
    'src/php/lib/class-UDiff.php',
    'src/theme/*php'
  ])
    .pipe(phpcs({
      bin: 'tools/phpcs.phar',
      standard: 'PSR12',
      colors: 1,
      warningSeverity: 0
    }))
    .pipe(phpcs.reporter('log'))
})

gulp.task('tests', gulp.series('test-sass', 'test-php'))

// --- build targets -----------------------------------------------------

gulp.task('clean', function () {
  const del = require('del')
  return del([
    [dirs.site] + '/**/*',
    [dirs.site] + '/**/.*'
  ])
})

gulp.task('theme-fonts', function () {
  return gulp.src([
    'src/theme/fonts/*/*woff',
    'src/theme/fonts/*/*woff2'
  ])
    .pipe(gulp.dest(dirs.theme + '/fonts/'))
})

gulp.task('theme-scss', gulp.series('test-sass', function () {
  const sass = require('gulp-sass')
  const concat = require('gulp-concat')
  const autoprefixer = require('gulp-autoprefixer')

  return gulp.src([
    'src/theme/scss/main.scss'
    // include additional vendor-css from /node_modules here
  ])
    .pipe(concat('style.css'))
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(sass({ outputStyle: 'compressed' }))
    .pipe(autoprefixer())
    .pipe(gulp.dest(dirs.theme))
}))

gulp.task('theme-php', function () {
  return gulp.src([
    'src/theme/**/*.php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.theme))
})

gulp.task('theme-I18N', function () {
  return gulp.src([
    'src/theme/I18N/**/*'
  ])
    .pipe(gulp.dest(dirs.theme + '/I18N'))
})

gulp.task('theme', gulp.parallel('theme-fonts', 'theme-scss', 'theme-php', 'theme-I18N'))

gulp.task('favicon', function () {
  const imagemin = require('gulp-imagemin')
  const imageminPngquant = require('imagemin-pngquant')

  return gulp.src([
    'src/favicon/**/*'
  ])
    .pipe(imagemin([
      imageminPngquant({ quality: [0.8, 0.9], strip: true })
    ], { verbose: true }))
    .pipe(replace('$NAME$', p.name, { skipBinary: true }))
    .pipe(gulp.dest(dirs.site))
})

gulp.task('meta', function () {
  return gulp.src([
    'src/robots.txt',
    'src/.config.ini',
    'src/.htpasswd',
    'src/.htaccess'
  ])
    .pipe(gulp.dest(dirs.site))
})

gulp.task('php', function () {
  return gulp.src([
    'src/php/**/*.php'
  ])
    .pipe(replace('$VERSION$', p.version, { skipBinary: true }))
    .pipe(replace('$URL$', p.homepage, { skipBinary: true }))
    .pipe(gulp.dest(dirs.site))
})

gulp.task('wiki', function () {
  return gulp.src([
    'wiki/**/*.md',
    'wiki/**/*.yaml'
  ])
    .pipe(gulp.dest(dirs.data))
})

gulp.task('docs', function () {
  return gulp.src([
    'docs/**/*.md'
  ])
    .pipe(gulp.dest(dirs.data + '/docs'))
})

gulp.task('dist', gulp.series(gulp.parallel('php', 'meta', 'favicon', 'theme', 'wiki'), 'docs'))

gulp.task('package-tgz', function () {
  const tar = require('gulp-tar')
  const gzip = require('gulp-gzip')
  const sort = require('gulp-sort')

  return gulp.src([
    dirs.build + '/wiki.md/**/*'
  ], { base: dirs.build })
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
  ], { base: dirs.build })
    .pipe(sort())
    .pipe(zip('wiki.md-' + p.version + '.zip'))
    .pipe(gulp.dest(dirs.build))
})

gulp.task('release', gulp.series('clean', 'dist', 'package-tgz', 'package-zip'))
