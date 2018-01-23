'use strict';

module.exports = function (grunt) {
  var gruntConfig = {
    copy: {
      dist: {
        expand: true,
        flatten: false,
        cwd: 'node_modules/raven-js/dist/',
        src: '*.*',
        dest: 'js/raven-js'
      }
    }
  };
  grunt.initConfig(gruntConfig);
  grunt.loadNpmTasks('grunt-contrib-copy');
  grunt.registerTask('default', ['copy']);
};
