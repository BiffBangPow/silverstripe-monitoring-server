const sass = require('gulp-sass')(require('sass'));
const autoprefixer = require('gulp-autoprefixer');
const babel = require('gulp-babel');
const cleanCSS = require('gulp-clean-css');
const { parallel, watch, src, dest } = require('gulp');
const uglify = require('gulp-uglify');
const sourcemaps = require('gulp-sourcemaps');
const mode = require('gulp-mode')({
    modes: ["production", "development"],
    default: "development",
    verbose: false
});

function processcss() {
    return src('client/src/scss/**/*.scss')
        .pipe(mode.development(sourcemaps.init()))
        .pipe(sass({
            includePaths: ['node_modules']
        })).on('error', sass.logError)
        .pipe(autoprefixer({'grid': 'no-autoplace'}))
        .pipe(mode.production(cleanCSS({level:1, inline: ['local']})))
        .pipe(mode.development(sourcemaps.write('../../dist/css/maps')))
        .pipe(dest('client/dist/css'))
}

function transpileJS() {
    return src('client/src/javascript/**/*.js')
        .pipe(mode.development(sourcemaps.init()))
        .pipe(babel({
            presets: [
                '@babel/preset-env'
            ]
        }))
        .pipe(mode.production(uglify()))
        .pipe(mode.development(sourcemaps.write('../../dist/javascript/maps')))
        .pipe(dest('client/dist/javascript'))
}


function watchfiles() {
    watch('client/src/javascript/**/*.js', {queue: false}, transpileJS);
    watch('client/src/scss/**/*.scss', {queue: false}, processcss);
}

exports.default = parallel(transpileJS, processcss);
exports.watch = watchfiles;
