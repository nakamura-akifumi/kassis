package main

import (
	"flag"
	"fmt"
	"io/fs"
	"os"
	"path/filepath"
	"strings"

	"github.com/nakamura-akifumi/kassis"
)

func main() {

	fmt.Printf("kassis document importer. version %s (Revison:%s)\n", kassiscore.VERSION, kassiscore.REVISION)
	fmt.Println("Main function started")

	flag.CommandLine.Usage = func() {
		o := flag.CommandLine.Output()
		_, _ = fmt.Fprint(o, "\nUsage: importer [COMMAND | help] [filepath | directory]\n")
		_, _ = fmt.Fprint(o, "Where COMMAND := [raw | dcndlrdf | isbn]\n")
		_, _ = fmt.Fprint(o, "      OPTIONS:\n")
		flag.PrintDefaults()
	}

	flag.Parse()

	if len(flag.Args()) != 2 {
		flag.CommandLine.Usage()
		os.Exit(1)
	}

	actions := []string{"raw", "ndlxml", "isbn"}
	if kassiscore.ArrayContains(actions, strings.ToLower(flag.Arg(0))) == false {
		fmt.Println("error: action is invalid")
		flag.CommandLine.Usage()
		return
	}
	actioname := strings.ToLower(flag.Arg(0))

	var files []string

	if f, err := os.Stat(flag.Arg(1)); os.IsNotExist(err) || f.IsDir() {
		// 指定の引数は存在するディレクトリ？
		if f, err := os.Stat(flag.Arg(1)); os.IsNotExist(err) || !f.IsDir() {
			fmt.Printf("Error: No such file or directory (%s)\n", flag.Arg(1))
			os.Exit(2)
		} else {

			err := filepath.WalkDir(flag.Arg(1), func(path string, info fs.DirEntry, err error) error {
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
		files = append(files, flag.Arg(1))
	}

	//fmt.Print(files)
	//fmt.Print("\n")
	fmt.Printf("files:%d\n", len(files))

	cfg := kassiscore.LoadConfig()
	fmt.Println("actionname:", actioname)

	err := kassiscore.CheckConfigAndConnections("error-only")
	if err != nil {
		os.Exit(10)
	}

	switch actioname {
	case "raw":
		err = kassiscore.ImportFromFile(files, cfg.Tika.Serveruri, cfg.Solr.Serveruri, cfg.Solr.Corename)
		if err != nil {
			fmt.Println(err)
			os.Exit(11)
		}
	case "dcndlrdf":
		err = kassiscore.ImportFromFileNCNDLRDF(files, cfg.Solr.Serveruri, cfg.Solr.Corename)
		if err != nil {
			fmt.Println(err)
			os.Exit(12)
		}
	case "isbn":
		_, err = kassiscore.ImportFromISBNFile(files, cfg.Solr.Serveruri, cfg.Solr.Corename)
		if err != nil {
			fmt.Println(err)
			os.Exit(13)
		}

	}
}
