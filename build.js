import * as esbuild from 'esbuild';
import { minify } from 'terser';
import { readFileSync, writeFileSync, mkdirSync, copyFileSync } from 'fs';
import { dirname } from 'path';
import { execSync } from 'child_process';

const watch = process.argv.includes('--watch');

const buildOptions = {
    entryPoints: ['resources/js/Diffyne.js'],
    bundle: true,
    format: 'iife',
    globalName: 'Diffyne',
    outfile: 'resources/dist/js/diffyne.js',
    platform: 'browser',
    target: 'es2020',
    logLevel: 'info',
    minify: false, // We'll use Terser for minification
};

async function build() {
    try {
        console.log('🔨 Building with esbuild...');
        
        // Build with esbuild
        await esbuild.build(buildOptions);
        
        console.log('✨ Minifying with Terser...');
        
        // Read the built file
        const code = readFileSync('resources/dist/js/diffyne.js', 'utf8');
        
        // Minify with Terser
        const minified = await minify(code, {
            compress: {
                drop_console: false,
                drop_debugger: true,
                pure_funcs: [],
            },
            mangle: {
                keep_classnames: true,
                keep_fnames: false,
            },
            format: {
                comments: false,
            },
        });
        
        // Write minified output
        writeFileSync('resources/dist/js/diffyne.js', minified.code);
        
        // Get file size
        const size = Buffer.byteLength(minified.code, 'utf8');
        const sizeKB = (size / 1024).toFixed(2);
        
        console.log(`✅ Build complete! Size: ${sizeKB} kB`);
        
    } catch (error) {
        console.error('❌ Build failed:', error);
        process.exit(1);
    }
}

if (watch) {
    console.log('👀 Watching for changes...');
    
    const ctx = await esbuild.context({
        ...buildOptions,
        plugins: [{
            name: 'terser-minify',
            setup(build) {
                build.onEnd(async (result) => {
                    if (result.errors.length === 0) {
                        console.log('✨ Minifying with Terser...');
                        const code = readFileSync('resources/dist/js/diffyne.js', 'utf8');
                        const minified = await minify(code, {
                            compress: {
                                drop_console: false,
                                drop_debugger: true,
                                pure_funcs: [],
                            },
                            mangle: {
                                keep_classnames: true,
                                keep_fnames: false,
                            },
                            format: {
                                comments: false,
                            },
                        });
                        writeFileSync('resources/dist/js/diffyne.js', minified.code);
                        
                        // Publish to public vendor directory
                        try {
                            console.log('📦 Publishing assets...');
                            execSync('php ../../artisan vendor:publish --tag=diffyne-assets --force', {
                                stdio: 'inherit'
                            });
                        } catch (error) {
                            console.error('⚠️  Failed to publish assets:', error.message);
                        }
                        
                        const size = Buffer.byteLength(minified.code, 'utf8');
                        const sizeKB = (size / 1024).toFixed(2);
                        console.log(`✅ Rebuild complete! Size: ${sizeKB} kB`);
                    }
                });
            }
        }]
    });
    
    await ctx.watch();
} else {
    await build();
}
