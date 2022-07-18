package main

import (
	"flag"
	"fmt"
	"io/fs"
	"os"
	"path/filepath"

	"github.com/nakamura-akifumi/kassis"
)

func main() {

	fmt.Printf("kassis document importer. version %s (Revison:%s)\n", kassiscore.VERSION, kassiscore.REVISION)
	fmt.Println("Main function started")
	flag.Parse()

	if len(flag.Args()) != 1 {
		fmt.Println("Error: no filepath or directory")
		os.Exit(1)
	}

	var files []string

	if f, err := os.Stat(flag.Arg(0)); os.IsNotExist(err) || f.IsDir() {
		// 指定の引数は存在するディレクトリ？
		if f, err := os.Stat(flag.Arg(0)); os.IsNotExist(err) || !f.IsDir() {
			fmt.Printf("Error: No such file or directory (%s)\n", flag.Arg(0))
			os.Exit(2)
		} else {

			err := filepath.WalkDir(flag.Arg(0), func(path string, info fs.DirEntry, err error) error {
				if err != nil {
					fmt.Println("Error: failed filepath.WalkDir")
					fmt.Println(err)
					os.Exit(3)
				}

				if info.IsDir() {
					return nil
				}

				files = append(files, path)
				return nil
			})
			if err != nil {
				fmt.Println("Error: failed filepath.WalkDir")
				fmt.Println(err)
				os.Exit(3)
			}
		}
	} else {
		// TODO: fullpath にしても良いかな。
		files = append(files, flag.Arg(0))
	}

	//fmt.Print(files)
	//fmt.Print("\n")
	fmt.Printf("files:%d\n", len(files))

	cfg := kassiscore.LoadConfig()
	err := kassiscore.ImportFromFile(files, cfg.Tika.Serveruri, cfg.Solr.Serveruri, cfg.Solr.Corename)
	if err != nil {
		fmt.Println(err)
		os.Exit(10)
	}
}
