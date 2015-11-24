"use strict";

var path = require('path')
var deepCopy = require('deepcopy')
var rootConfig = deepCopy(require(path.join('..', '..', '..', '..', 'root.config.js')))

var base = __dirname

var entry = path.join(base, 'grade-student-raw.js')

var webpackConfig = rootConfig.webpackConfig

webpackConfig.entry = entry

webpackConfig.output = {
  base: base,
  filename: 'grade-student.js'
}

function gulpTaskFn(gulp, plugins) {
  return function() {
    return gulp.src(entry)
      .pipe(plugins.webpack(webpackConfig, require('webpack')))
      .pipe(gulp.dest(base))
  }
}

module.exports = {
  gulpTaskName: 'webpack-admin-academics-assessment-grade-student',
  gulpTaskFn: gulpTaskFn,
  minifyJs: [path.join(base, 'grade-student.js')]
}
